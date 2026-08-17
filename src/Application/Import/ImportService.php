<?php

namespace EventFlow\Application\Import;

use DateInterval;
use EventFlow\Application\Audit\AuditAction;
use EventFlow\Application\Audit\AuditEntityType;
use EventFlow\Application\Audit\AuditEvent;
use EventFlow\Application\Audit\AuditService;
use EventFlow\Application\Authorization\AuthorizationService;
use EventFlow\Application\Authorization\AuthorizationException;
use EventFlow\Application\Authorization\Capability;
use EventFlow\Application\Authorization\PrincipalContext;
use EventFlow\Application\Authorization\PrincipalType;
use EventFlow\Application\Clock\Clock;
use EventFlow\Application\Idempotency\IdempotencyOutcome;
use EventFlow\Application\Idempotency\IdempotencyResultReference;
use EventFlow\Application\Idempotency\IdempotencyService;
use EventFlow\Application\Idempotency\IdempotentOperationResult;
use EventFlow\Application\Invitation\CreateInvitation;
use EventFlow\Application\Invitation\InvitationException;
use EventFlow\Application\Persistence\EventScope;
use EventFlow\Application\Security\SecureRandom;
use EventFlow\Application\Transaction\TransactionManager;
use InvalidArgumentException;

final readonly class ImportService
{
    public function __construct(
        private ImportRepository $imports,
        private TabularSourceParser $parser,
        private ImportNormalizer $normalizer,
        private InvitationImportPort $invitations,
        private AuthorizationService $authorization,
        private IdempotencyService $idempotency,
        private AuditService $audit,
        private Clock $clock,
        private SecureRandom $random,
        private TransactionManager $transactions,
    ) {}

    public function stage(PrincipalContext $principal, EventScope $scope, string $path, string $idempotencyKey): IdempotencyOutcome
    {
        $this->authorization->requireEventCapability($principal, $scope, Capability::MANAGE_IMPORTS);
        $source = $this->parser->parse($path);
        return $this->idempotency->execute(
            $principal, $scope, 'import.stage', $idempotencyKey,
            ['event_id' => $scope->eventId, 'filename' => $source->filename, 'file_hash' => $source->fileHash],
            function () use ($principal, $scope, $source): IdempotentOperationResult {
                $job = $this->imports->createStaged($scope, $source, $source->rows, $this->actorUserId($principal), $this->clock->now());
                return new IdempotentOperationResult(new IdempotencyResultReference('import_job', $job->jobId, 201), $job);
            },
        );
    }

    public function validate(PrincipalContext $principal, EventScope $scope, int $jobId, ImportMapping $mapping, string $idempotencyKey): IdempotencyOutcome
    {
        return $this->idempotency->execute(
            $principal, $scope, 'import.validate', $idempotencyKey,
            ['event_id' => $scope->eventId, 'job_id' => $jobId, 'mapping' => $mapping->columns],
            function () use ($principal, $scope, $jobId, $mapping): IdempotentOperationResult {
                $this->authorization->requireEventCapability($principal, $scope, Capability::MANAGE_IMPORTS);
                $job = $this->requiredJob($scope, $jobId, [ImportStatus::STAGED]);
                $valid = 0; $invalid = 0; $warning = 0;
                foreach ($this->imports->rowsForValidation($scope, $jobId) as $row) {
                    $result = $this->normalizer->normalize($row->rawData, $mapping);
                    $status = $result['errors'] === [] ? ImportRowStatus::READY : ImportRowStatus::INVALID;
                    $this->imports->storeValidation($row, $status, $result['normalized'], $result['errors'], $result['warnings'], $this->clock->now());
                    $status === ImportRowStatus::READY ? $valid++ : $invalid++;
                    if ($result['warnings'] !== []) $warning++;
                }
                $updated = $this->imports->finishValidation($job, $valid, $invalid, $warning, $mapping->columns, $this->clock->now());
                $dryRun = new ImportDryRun($job->totalRows, $valid, $invalid, $warning);
                return new IdempotentOperationResult(new IdempotencyResultReference('import_job', $jobId, 200), $dryRun);
            },
        );
    }

    public function applyBatch(PrincipalContext $principal, EventScope $scope, int $jobId, string $workerOwner, int $limit = 100): ImportApplyResult
    {
        $this->authorization->requireEventCapability($principal, $scope, Capability::MANAGE_IMPORTS);
        if ($workerOwner === '' || strlen($workerOwner) > 190 || $limit < 1 || $limit > 500) throw new ImportException('import_worker_request_invalid');
        $now = $this->clock->now(); $token = $this->random->hex(16); $leaseExpiry = $now->add(new DateInterval('PT60S'));
        $job = $this->transactions->transactional(fn (): ?ImportJobRecord => $this->imports->acquireLease($scope, $jobId, $workerOwner, $token, $now, $leaseExpiry));
        if ($job === null) throw new ImportException('import_worker_lease_unavailable');
        $processed = 0; $applied = 0; $failed = 0;
        foreach ($this->imports->readyBatch($job, $token, $this->clock->now(), $limit) as $row) {
            $processed++;
            try {
                $data = $row->normalizedData ?? throw new ImportException('import_row_not_normalized');
                $outcome = $this->invitations->createImported(
                    $principal,
                    new CreateInvitation($scope, (string) $data['primary_name'], (int) $data['capacity'], $data['primary_email'] ?? null, $data['primary_phone'] ?? null),
                    'import-row-' . $jobId . '-' . $row->rowId,
                );
            } catch (ImportException|InvalidArgumentException|InvitationException|AuthorizationException $exception) {
                $this->imports->markFailed($row, $exception instanceof ImportException ? $exception->safeCode : 'import_row_apply_failed', $this->clock->now());
                $failed++;
                $leaseExpiry = $this->clock->now()->add(new DateInterval('PT60S'));
                $this->imports->heartbeat($job, $token, $this->clock->now(), $leaseExpiry);
                continue;
            }
            $this->imports->markApplied($row, $outcome->reference->entityId, $this->clock->now());
            $applied++;
            $leaseExpiry = $this->clock->now()->add(new DateInterval('PT60S'));
            $this->imports->heartbeat($job, $token, $this->clock->now(), $leaseExpiry);
        }
        $reconciled = $this->transactions->transactional(function () use ($principal, $scope, $job, $jobId, $token): ImportJobRecord {
            $reconciled = $this->imports->reconcile($job, $token, $this->clock->now());
            if ($reconciled->status === ImportStatus::COMPLETED) {
                $this->audit->recordRequired(new AuditEvent(principal: $principal, eventScope: $scope, action: AuditAction::IMPORT_APPLIED, entityType: AuditEntityType::IMPORT_JOB, entityId: $jobId, after: ['applied_rows' => $reconciled->appliedRows, 'failed_rows' => $reconciled->failedRows]));
            }
            return $reconciled;
        });
        return new ImportApplyResult($reconciled, $processed, $applied, $failed);
    }

    /** @param list<ImportStatus> $allowed */
    private function requiredJob(EventScope $scope, int $jobId, array $allowed): ImportJobRecord
    {
        $job = $this->imports->lockJob($scope, $jobId);
        if ($job === null) throw new ImportException('import_job_not_found');
        if (!in_array($job->status, $allowed, true)) throw new ImportException('import_transition_invalid');
        return $job;
    }
    private function actorUserId(PrincipalContext $principal): ?int { return $principal->type === PrincipalType::WORDPRESS_USER ? $principal->userId : null; }
}
