<?php

namespace EventFlow\Tests\Unit\Application\Import;

use DateTimeImmutable;
use DateTimeZone;
use EventFlow\Application\Audit\AuditCanonicalizer;
use EventFlow\Application\Audit\AuditPayloadRedactor;
use EventFlow\Application\Audit\AuditRecord;
use EventFlow\Application\Audit\AuditRepository;
use EventFlow\Application\Audit\AuditService;
use EventFlow\Application\Authorization\AuthorizationService;
use EventFlow\Application\Authorization\Capability;
use EventFlow\Application\Authorization\EventRole;
use EventFlow\Application\Authorization\GlobalRecoveryAuthority;
use EventFlow\Application\Authorization\MembershipReader;
use EventFlow\Application\Authorization\MembershipSnapshot;
use EventFlow\Application\Authorization\PrincipalContext;
use EventFlow\Application\Authorization\RoleCapabilityPolicy;
use EventFlow\Application\Clock\Clock;
use EventFlow\Application\Idempotency\CanonicalRequestHasher;
use EventFlow\Application\Idempotency\IdempotencyClaimResult;
use EventFlow\Application\Idempotency\IdempotencyClaimState;
use EventFlow\Application\Idempotency\IdempotencyRecord;
use EventFlow\Application\Idempotency\IdempotencyRepository;
use EventFlow\Application\Idempotency\IdempotencyRequest;
use EventFlow\Application\Idempotency\IdempotencyResultReference;
use EventFlow\Application\Idempotency\IdempotencyService;
use EventFlow\Application\Import\ImportJobRecord;
use EventFlow\Application\Import\ImportMapping;
use EventFlow\Application\Import\ImportNormalizer;
use EventFlow\Application\Import\ImportRepository;
use EventFlow\Application\Import\ImportRowRecord;
use EventFlow\Application\Import\ImportRowStatus;
use EventFlow\Application\Import\ImportService;
use EventFlow\Application\Import\ImportStatus;
use EventFlow\Application\Import\ParsedImportSource;
use EventFlow\Application\Import\TabularSourceParser;
use EventFlow\Application\Invitation\CreateInvitation;
use EventFlow\Application\Invitation\InvitationRecord;
use EventFlow\Application\Invitation\InvitationRepository;
use EventFlow\Application\Invitation\InvitationService;
use EventFlow\Application\Invitation\InvitationStatus;
use EventFlow\Application\Persistence\EventScope;
use EventFlow\Application\Security\CredentialDigester;
use EventFlow\Application\Security\SecureRandom;
use EventFlow\Application\Transaction\TransactionManager;
use EventFlow\Application\Transaction\TransactionOptions;
use PHPUnit\Framework\TestCase;

final class ImportServiceTest extends TestCase
{
    public function testStageValidateAndApplyCreatesOnlyValidInvitationWithoutRawCredentialPersistence(): void
    {
        $fixture = new ImportFixture();
        $job = $fixture->service->stage($fixture->user, $fixture->scope, 'ignored.csv', 'stage-001')->response;
        $dryRun = $fixture->service->validate($fixture->user, $fixture->scope, $job->jobId, new ImportMapping(['primary_name' => 'Name', 'primary_email' => 'Email', 'capacity' => 'Capacity']), 'validate-001')->response;

        self::assertSame(1, $dryRun->readyRows);
        self::assertSame(1, $dryRun->invalidRows);
        $result = $fixture->service->applyBatch($fixture->worker, $fixture->scope, $job->jobId, 'worker-a');
        self::assertSame(ImportStatus::COMPLETED, $result->job->status);
        self::assertSame(1, $result->appliedRows);
        self::assertSame(1, $fixture->invitations->created);
        self::assertSame(32, strlen($fixture->invitations->lastDigest));
    }

    public function testExpiredLeaseCanBeReclaimedWithoutDuplicateInvitationMutation(): void
    {
        $fixture = new ImportFixture();
        $job = $fixture->service->stage($fixture->user, $fixture->scope, 'ignored.csv', 'stage-002')->response;
        $fixture->service->validate($fixture->user, $fixture->scope, $job->jobId, new ImportMapping(['primary_name' => 'Name', 'primary_email' => 'Email', 'capacity' => 'Capacity']), 'validate-002');
        $fixture->imports->failNextMark = true;
        try { $fixture->service->applyBatch($fixture->worker, $fixture->scope, $job->jobId, 'worker-crash'); self::fail('Expected simulated crash.'); } catch (\RuntimeException) {}
        self::assertSame(1, $fixture->invitations->created);
        $fixture->clock->advance(61);
        $result = $fixture->service->applyBatch($fixture->worker, $fixture->scope, $job->jobId, 'worker-resume');
        self::assertSame(ImportStatus::COMPLETED, $result->job->status);
        self::assertSame(1, $fixture->invitations->created);
    }
}

final class ImportFixture
{
    public readonly EventScope $scope; public readonly PrincipalContext $user; public readonly PrincipalContext $worker; public readonly ImportMemoryRepository $imports; public readonly ImportInvitationRepository $invitations; public readonly ImportService $service; public readonly ImportClock $clock;
    public function __construct()
    {
        $this->scope = new EventScope(100); $this->user = PrincipalContext::wordpressUser(7); $this->worker = PrincipalContext::backgroundJob(9, $this->scope, [Capability::MANAGE_IMPORTS]); $this->clock = new ImportClock(); $transactions = new ImportTransactions(); $random = new ImportRandom(); $authorization = new AuthorizationService(new ImportMembershipReader(), new RoleCapabilityPolicy(), $this->clock, new ImportNoRecovery()); $idempotency = new IdempotencyService(new ImportIdempotencyRepository(), $transactions, $this->clock, $random, new CanonicalRequestHasher()); $audit = new AuditService(new ImportAuditRepository(), $transactions, $this->clock, new AuditPayloadRedactor(), new AuditCanonicalizer()); $this->invitations = new ImportInvitationRepository();
        $invitationService = new InvitationService($this->invitations, $authorization, $idempotency, $audit, $this->clock, $random, new CredentialDigester()); $this->imports = new ImportMemoryRepository($this->scope, $this->clock);
        $this->service = new ImportService($this->imports, new ImportTestParser(), new ImportNormalizer(), $invitationService, $authorization, $idempotency, $audit, $this->clock, $random, $transactions);
    }
}

final class ImportTestParser implements TabularSourceParser { public function parse(string $path): ParsedImportSource { return new ParsedImportSource('guests.csv', str_repeat('a', 64), ['Name', 'Email', 'Capacity'], [['Name' => 'Valid Guest', 'Email' => 'valid@example.com', 'Capacity' => '2'], ['Name' => '', 'Email' => 'bad', 'Capacity' => '0']]); } }

final class ImportMemoryRepository implements ImportRepository
{
    public ImportJobRecord $job; /** @var array<int,ImportRowRecord> */ public array $rows = []; public bool $failNextMark = false;
    public function __construct(private readonly EventScope $scope, private readonly ImportClock $clock) {}
    public function createStaged(EventScope $scope, ParsedImportSource $source, array $rows, ?int $actorUserId, DateTimeImmutable $now): ImportJobRecord { $this->job = new ImportJobRecord(1, $scope, ImportStatus::STAGED, $source->filename, $source->fileHash, count($rows), 0, 0, 0, 0); foreach ($rows as $i => $raw) $this->rows[$i + 1] = new ImportRowRecord($i + 1, 1, $i + 1, ImportRowStatus::PENDING, $raw); return $this->job; }
    public function lockJob(EventScope $scope, int $jobId): ?ImportJobRecord { return $this->job ?? null; }
    public function rowsForValidation(EventScope $scope, int $jobId): array { return array_values(array_filter($this->rows, static fn (ImportRowRecord $r): bool => $r->status === ImportRowStatus::PENDING)); }
    public function storeValidation(ImportRowRecord $row, ImportRowStatus $status, ?array $normalized, array $errors, array $warnings, DateTimeImmutable $now): void { $this->rows[$row->rowId] = new ImportRowRecord($row->rowId, 1, $row->sourceRowNumber, $status, $row->rawData, $normalized, $errors, $warnings); }
    public function finishValidation(ImportJobRecord $job, int $validRows, int $invalidRows, int $warningRows, array $mapping, DateTimeImmutable $now): ImportJobRecord { return $this->job = new ImportJobRecord(1, $this->scope, ImportStatus::VALIDATED, $job->sourceFilename, $job->sourceFileHash, $job->totalRows, $validRows, $invalidRows, 0, 0); }
    public function acquireLease(EventScope $scope, int $jobId, string $owner, string $token, DateTimeImmutable $now, DateTimeImmutable $expiresAt): ?ImportJobRecord { if (isset($this->job) && $this->job->leaseExpiresAt !== null && $this->job->leaseExpiresAt > $now) return null; return $this->job = new ImportJobRecord(1, $scope, ImportStatus::APPLYING, $this->job->sourceFilename, $this->job->sourceFileHash, $this->job->totalRows, $this->job->validRows, $this->job->invalidRows, $this->job->appliedRows, $this->job->failedRows, $token, $expiresAt); }
    public function heartbeat(ImportJobRecord $job, string $token, DateTimeImmutable $now, DateTimeImmutable $expiresAt): void { $this->job = new ImportJobRecord(1, $this->scope, ImportStatus::APPLYING, $job->sourceFilename, $job->sourceFileHash, $job->totalRows, $job->validRows, $job->invalidRows, $job->appliedRows, $job->failedRows, $token, $expiresAt); }
    public function readyBatch(ImportJobRecord $job, string $token, DateTimeImmutable $now, int $limit): array { return array_slice(array_values(array_filter($this->rows, static fn (ImportRowRecord $r): bool => $r->status === ImportRowStatus::READY)), 0, $limit); }
    public function markApplied(ImportRowRecord $row, int $invitationId, DateTimeImmutable $now): void { if ($this->failNextMark) { $this->failNextMark = false; throw new \RuntimeException('simulated_worker_crash'); } $this->rows[$row->rowId] = new ImportRowRecord($row->rowId, 1, $row->sourceRowNumber, ImportRowStatus::APPLIED, $row->rawData, $row->normalizedData); }
    public function markFailed(ImportRowRecord $row, string $safeCode, DateTimeImmutable $now): void { $this->rows[$row->rowId] = new ImportRowRecord($row->rowId, 1, $row->sourceRowNumber, ImportRowStatus::FAILED, $row->rawData, $row->normalizedData, [$safeCode]); }
    public function reconcile(ImportJobRecord $job, string $token, DateTimeImmutable $now): ImportJobRecord { $applied = count(array_filter($this->rows, static fn (ImportRowRecord $r): bool => $r->status === ImportRowStatus::APPLIED)); $failed = count(array_filter($this->rows, static fn (ImportRowRecord $r): bool => $r->status === ImportRowStatus::FAILED)); $remaining = count(array_filter($this->rows, static fn (ImportRowRecord $r): bool => $r->status === ImportRowStatus::READY)); return $this->job = new ImportJobRecord(1, $this->scope, $remaining === 0 ? ImportStatus::COMPLETED : ImportStatus::APPLYING, $job->sourceFilename, $job->sourceFileHash, $job->totalRows, $job->validRows, $job->invalidRows, $applied, $failed); }
}

final class ImportInvitationRepository implements InvitationRepository
{
    public int $created = 0; public string $lastDigest = '';
    public function create(CreateInvitation $command, string $code, string $tokenDigest, ?int $actorUserId, DateTimeImmutable $now): InvitationRecord { $this->created++; $this->lastDigest = $tokenDigest; return new InvitationRecord($this->created, $command->eventScope, $code, $command->primaryName, $command->capacity, InvitationStatus::ACTIVE, 1, null); }
    public function lock(EventScope $scope, int $invitationId): ?InvitationRecord { return null; }
    public function rotateCredential(InvitationRecord $invitation, string $tokenDigest, ?DateTimeImmutable $expiresAt, ?int $actorUserId, DateTimeImmutable $now): InvitationRecord { return $invitation; }
    public function revoke(InvitationRecord $invitation, ?int $actorUserId, DateTimeImmutable $now): InvitationRecord { return $invitation; }
    public function reactivate(InvitationRecord $invitation, string $tokenDigest, ?DateTimeImmutable $expiresAt, ?int $actorUserId, DateTimeImmutable $now): InvitationRecord { return $invitation; }
    public function invalidateGuestAccess(EventScope $scope, int $invitationId, DateTimeImmutable $now): void {}
}

final class ImportClock implements Clock { private DateTimeImmutable $now; public function __construct() { $this->now = new DateTimeImmutable('2026-08-16 18:00:00', new DateTimeZone('UTC')); } public function now(): DateTimeImmutable { return $this->now; } public function advance(int $seconds): void { $this->now = $this->now->modify('+' . $seconds . ' seconds'); } }
final class ImportRandom implements SecureRandom { private int $i = 1; public function hex(int $bytes): string { return str_pad(dechex($this->i++), $bytes * 2, 'a', STR_PAD_LEFT); } }
final class ImportTransactions implements TransactionManager { private int $d = 0; public function transactional(callable $operation, ?TransactionOptions $options = null): mixed { $this->d++; try { return $operation(); } finally { $this->d--; } } public function isActive(): bool { return $this->d > 0; } public function assertNotActive(): void { if ($this->d) throw new \RuntimeException('active'); } }
final class ImportMembershipReader implements MembershipReader { public function findCurrent(EventScope $eventScope, int $userId): ?MembershipSnapshot { return new MembershipSnapshot(1, $eventScope, $userId, EventRole::OWNER, true, null); } }
final readonly class ImportNoRecovery implements GlobalRecoveryAuthority { public function canRecoverPrimaryOwnership(int $userId): bool { return false; } }
final class ImportAuditRepository implements AuditRepository { /** @var list<AuditRecord> */ private array $r = []; public function lockChainHead(?EventScope $eventScope): ?string { return $this->r === [] ? null : $this->r[array_key_last($this->r)]->recordHash; } public function append(AuditRecord $record): int { $this->r[] = $record; return count($this->r); } }
final class ImportIdempotencyRepository implements IdempotencyRepository
{
    /** @var array<string,IdempotencyRecord> */ private array $r = [];
    public function claim(IdempotencyRequest $q, string $l, DateTimeImmutable $n, DateTimeImmutable $le, DateTimeImmutable $re): IdempotencyClaimResult { $k = $q->principalScope . $q->operationName . bin2hex($q->keyDigest); if (isset($this->r[$k])) return new IdempotencyClaimResult(IdempotencyClaimState::REPLAY, $this->r[$k]); return new IdempotencyClaimResult(IdempotencyClaimState::ACQUIRED, $this->r[$k] = new IdempotencyRecord(count($this->r) + 1, $q->requestFingerprint, 'in_progress', $le, null, false)); }
    public function complete(int $id, string $l, IdempotencyResultReference $ref, bool $s, DateTimeImmutable $at): void { foreach ($this->r as $k => $v) if ($v->recordId === $id) $this->r[$k] = new IdempotencyRecord($id, $v->requestFingerprint, 'completed', null, $ref, $s); }
    public function fail(int $id, string $l, DateTimeImmutable $at): void { foreach ($this->r as $k => $v) if ($v->recordId === $id) unset($this->r[$k]); }
}
