<?php

namespace EventFlow\Application\Privacy;

use DateTimeImmutable;
use Throwable;
use EventFlow\Application\Audit\{AuditAction, AuditEntityType, AuditEvent, AuditService};
use EventFlow\Application\Authorization\{AuthorizationService, Capability, PrincipalContext, PrincipalType};
use EventFlow\Application\Clock\Clock;
use EventFlow\Application\Export\ExportArtifactStorage;
use EventFlow\Application\Health\PrivacyReconciliationGate;
use EventFlow\Application\Idempotency\{IdempotencyOutcome, IdempotencyResultReference, IdempotencyService, IdempotentOperationResult};
use EventFlow\Application\Job\{JobRecord, JobRepository, JobRequest};
use EventFlow\Application\Persistence\EventScope;
use EventFlow\Application\Transaction\TransactionManager;

final readonly class PrivacyService implements PrivacyReconciliationGate, PrivacyCommands
{
    public function __construct(
        private PrivacyRepository $repository,
        private ExportArtifactStorage $artifacts,
        private JobRepository $jobs,
        private AuthorizationService $authorization,
        private IdempotencyService $idempotency,
        private AuditService $audit,
        private Clock $clock,
        private TransactionManager $transactions,
    ) {
    }

    public function request(
        PrincipalContext $principal,
        EventScope $scope,
        int $invitationId,
        string $policyVersion,
        string $purpose,
        string $idempotencyKey,
    ): IdempotencyOutcome {
        $this->validate($invitationId, $policyVersion, $purpose);
        $this->authorization->requireEventCapability($principal, $scope, Capability::MANAGE_PRIVACY);
        return $this->idempotency->execute(
            $principal,
            $scope,
            'privacy.request',
            $idempotencyKey,
            ['invitation_id' => $invitationId, 'policy_version' => $policyVersion, 'purpose' => trim($purpose)],
            function () use ($principal, $scope, $invitationId, $policyVersion, $purpose): IdempotentOperationResult {
                $this->authorization->requireEventCapability($principal, $scope, Capability::MANAGE_PRIVACY);
                $now = $this->clock->now();
                $action = $this->repository->createAction($scope, $invitationId, 'explicit', $policyVersion, trim($purpose), $principal->userId, $now);
                $this->enqueue($action, $now);
                $this->audit->recordRequired(new AuditEvent(
                    $principal, $scope, AuditAction::PRIVACY_ACTION_STARTED, AuditEntityType::PRIVACY_ACTION,
                    $action->privacyActionId, after: ['policy_version' => $policyVersion, 'request_kind' => 'explicit', 'purpose_digest' => hash('sha256', trim($purpose))],
                ));
                return new IdempotentOperationResult(new IdempotencyResultReference('privacy_action', $action->privacyActionId, 202), $action);
            },
        );
    }

    public function execute(JobRecord $job): PrivacyActionRecord
    {
        if ($job->jobType !== 'privacy.execute' || $job->eventScope === null || (int) ($job->payload['privacy_action_id'] ?? 0) < 1) {
            throw new PrivacyException('privacy_job_invalid');
        }
        $this->authorization->requireEventCapability($job->principal(), $job->eventScope, Capability::MANAGE_PRIVACY);
        $action = $this->transactions->transactional(fn (): PrivacyActionRecord => $this->repository->resume($job->eventScope, (int) $job->payload['privacy_action_id'], $this->clock->now()));

        try {
            while ($action->status !== 'completed') {
                $action = match ($action->checkpoint) {
                    'requested' => $this->step($action, fn () => $this->repository->revokeCredentials($action, $this->clock->now()), 'credentials_revoked'),
                    'credentials_revoked' => $this->step($action, fn () => $this->repository->minimizePii($action, $this->clock->now()), 'pii_minimized'),
                    'pii_minimized' => $this->invalidateExports($action),
                    'exports_invalidated' => $this->deleteArtifacts($action),
                    'artifacts_deleted' => $this->step($action, fn () => $this->repository->recordTombstone($action, $this->clock->now()), 'tombstone_recorded'),
                    'tombstone_recorded' => $this->finish($job->principal(), $action),
                    'completed' => $action,
                    default => throw new PrivacyException('privacy_checkpoint_invalid'),
                };
            }
            return $action;
        } catch (Throwable $failure) {
            $code = $failure instanceof PrivacyException ? $failure->safeCode : 'privacy_execution_failed';
            $this->transactions->transactional(fn () => $this->repository->fail($action, $code, $this->clock->now()));
            throw $failure;
        }
    }

    public function scheduleRetention(EventScope $scope, int $invitationId, string $policyVersion, string $reason): PrivacyActionRecord
    {
        $this->validate($invitationId, $policyVersion, $reason);
        return $this->transactions->transactional(function () use ($scope, $invitationId, $policyVersion, $reason): PrivacyActionRecord {
            $principal = PrincipalContext::system('retention');
            $now = $this->clock->now();
            $action = $this->repository->createAction($scope, $invitationId, 'retention', $policyVersion, trim($reason), null, $now);
            $this->enqueue($action, $now);
            $this->audit->recordRequired(new AuditEvent($principal, $scope, AuditAction::PRIVACY_ACTION_STARTED, AuditEntityType::PRIVACY_ACTION, $action->privacyActionId, after: ['policy_version' => $policyVersion, 'request_kind' => 'retention', 'purpose_digest' => hash('sha256', trim($reason))]));
            return $action;
        });
    }

    public function placeHold(PrincipalContext $principal, EventScope $scope, ?int $invitationId, string $policyVersion, string $reason, string $idempotencyKey): IdempotencyOutcome
    {
        if ($principal->userId === null || ($invitationId !== null && $invitationId < 1) || !preg_match('/^[A-Za-z0-9][A-Za-z0-9._-]{0,63}$/', $policyVersion) || trim($reason) === '') {
            throw new PrivacyException('retention_hold_invalid');
        }
        $this->authorization->requireEventCapability($principal, $scope, Capability::MANAGE_PRIVACY);
        return $this->idempotency->execute($principal, $scope, 'privacy.hold.place', $idempotencyKey, ['invitation_id' => $invitationId, 'policy_version' => $policyVersion, 'reason' => trim($reason)], function () use ($principal, $scope, $invitationId, $policyVersion, $reason): IdempotentOperationResult {
            $this->authorization->requireEventCapability($principal, $scope, Capability::MANAGE_PRIVACY);
            $hold = $this->repository->placeHold($scope, $invitationId, $policyVersion, trim($reason), $principal->userId, $this->clock->now());
            $this->audit->recordRequired(new AuditEvent($principal, $scope, AuditAction::RETENTION_HOLD_PLACED, AuditEntityType::RETENTION_HOLD, $hold->retentionHoldId, after: ['policy_version' => $policyVersion, 'scope' => $invitationId === null ? 'event' : 'invitation', 'reason_digest' => hash('sha256', trim($reason))]));
            return new IdempotentOperationResult(new IdempotencyResultReference('retention_hold', $hold->retentionHoldId, 201), $hold);
        });
    }

    public function releaseHold(PrincipalContext $principal, EventScope $scope, int $holdId, string $idempotencyKey): IdempotencyOutcome
    {
        if ($principal->userId === null || $holdId < 1) {
            throw new PrivacyException('retention_hold_invalid');
        }
        $this->authorization->requireEventCapability($principal, $scope, Capability::MANAGE_PRIVACY);
        return $this->idempotency->execute($principal, $scope, 'privacy.hold.release', $idempotencyKey, ['retention_hold_id' => $holdId], function () use ($principal, $scope, $holdId): IdempotentOperationResult {
            $this->authorization->requireEventCapability($principal, $scope, Capability::MANAGE_PRIVACY);
            $hold = $this->repository->releaseHold($scope, $holdId, $principal->userId, $this->clock->now());
            $this->audit->recordRequired(new AuditEvent($principal, $scope, AuditAction::RETENTION_HOLD_RELEASED, AuditEntityType::RETENTION_HOLD, $holdId, after: ['status' => 'released']));
            return new IdempotentOperationResult(new IdempotencyResultReference('retention_hold', $holdId, 200), $hold);
        });
    }

    public function isReconciled(): bool
    {
        return $this->repository->isReconciled();
    }

    public function requirePostRestoreReconciliation(): int
    {
        return $this->transactions->transactional(function (): int {
            $count = $this->repository->requireReconciliation($this->clock->now());
            $this->audit->recordRequired(new AuditEvent(PrincipalContext::system('restore-detected'), null, AuditAction::PRIVACY_RECONCILIATION_REQUIRED, AuditEntityType::PLATFORM, after: ['privacy_states_pending' => $count]));
            return $count;
        });
    }

    public function reconcileRestoredState(): int
    {
        $completed = 0;
        foreach ($this->repository->pendingReconciliation() as $action) {
            $this->transactions->transactional(fn () => $this->repository->revokeCredentials($action, $this->clock->now()));
            $this->transactions->transactional(fn () => $this->repository->minimizePii($action, $this->clock->now()));
            $this->transactions->transactional(fn () => $this->repository->invalidatePiiExports($action, $this->clock->now()));
            $this->transactions->assertNotActive();
            foreach ($this->repository->invalidatedArtifactLocators($action) as $locator) {
                $this->artifacts->delete($locator);
            }
            $this->transactions->transactional(function () use ($action): void {
                $this->repository->recordTombstone($action, $this->clock->now());
                $this->audit->recordRequired(new AuditEvent(PrincipalContext::system('privacy-reconciliation'), $action->eventScope, AuditAction::PRIVACY_RECONCILED, AuditEntityType::PRIVACY_ACTION, $action->privacyActionId, after: ['policy_version' => $action->policyVersion, 'result' => 'forward_reapplied']));
            });
            $completed++;
        }
        return $completed;
    }

    private function step(PrivacyActionRecord $action, callable $operation, string $checkpoint): PrivacyActionRecord
    {
        return $this->transactions->transactional(function () use ($action, $operation, $checkpoint): PrivacyActionRecord {
            $operation();
            return $this->repository->advance($action, $checkpoint, $this->clock->now());
        });
    }

    private function invalidateExports(PrivacyActionRecord $action): PrivacyActionRecord
    {
        return $this->transactions->transactional(function () use ($action): PrivacyActionRecord {
            $this->repository->invalidatePiiExports($action, $this->clock->now());
            return $this->repository->advance($action, 'exports_invalidated', $this->clock->now());
        });
    }

    private function deleteArtifacts(PrivacyActionRecord $action): PrivacyActionRecord
    {
        $this->transactions->assertNotActive();
        foreach ($this->repository->invalidatedArtifactLocators($action) as $locator) {
            $this->artifacts->delete($locator);
        }
        return $this->transactions->transactional(fn (): PrivacyActionRecord => $this->repository->advance($action, 'artifacts_deleted', $this->clock->now()));
    }

    private function finish(PrincipalContext $principal, PrivacyActionRecord $action): PrivacyActionRecord
    {
        return $this->transactions->transactional(function () use ($principal, $action): PrivacyActionRecord {
            $completed = $this->repository->complete($action, $this->clock->now());
            $this->audit->recordRequired(new AuditEvent($principal, $action->eventScope, AuditAction::PRIVACY_ACTION_COMPLETED, AuditEntityType::PRIVACY_ACTION, $action->privacyActionId, after: ['policy_version' => $action->policyVersion, 'result' => 'anonymized']));
            return $completed;
        });
    }

    private function enqueue(PrivacyActionRecord $action, DateTimeImmutable $now): void
    {
        $this->jobs->enqueue(JobRequest::create($action->eventScope, 'privacy.execute', 1, ['privacy_action_id' => $action->privacyActionId, 'policy_version' => $action->policyVersion], [Capability::MANAGE_PRIVACY], $now, 200, 20, 'privacy:' . $action->privacyActionId), $now);
    }

    private function validate(int $invitationId, string $policyVersion, string $purpose): void
    {
        if ($invitationId < 1 || !preg_match('/^[A-Za-z0-9][A-Za-z0-9._-]{0,63}$/', $policyVersion) || trim($purpose) === '' || strlen($purpose) > 500) {
            throw new PrivacyException('privacy_request_invalid');
        }
    }
}
