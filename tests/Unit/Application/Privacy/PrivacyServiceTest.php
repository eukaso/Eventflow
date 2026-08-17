<?php

namespace EventFlow\Tests\Unit\Application\Privacy;

use DateTimeImmutable;
use DateTimeZone;
use RuntimeException;
use EventFlow\Application\Audit\{AuditCanonicalizer, AuditPayloadRedactor, AuditRecord, AuditRepository, AuditService};
use EventFlow\Application\Authorization\{AuthorizationService, Capability, EventRole, GlobalRecoveryAuthority, MembershipReader, MembershipSnapshot, PrincipalContext, RoleCapabilityPolicy};
use EventFlow\Application\Clock\Clock;
use EventFlow\Application\Export\{ExportArtifactStorage, ExportRecord, PublishedExportArtifact};
use EventFlow\Application\Idempotency\{CanonicalRequestHasher, IdempotencyClaimResult, IdempotencyClaimState, IdempotencyRecord, IdempotencyRepository, IdempotencyRequest, IdempotencyResultReference, IdempotencyService};
use EventFlow\Application\Job\{JobRecord, JobReconciliationResult, JobRepository, JobRequest, JobStatus};
use EventFlow\Application\Persistence\EventScope;
use EventFlow\Application\Privacy\{PrivacyActionRecord, PrivacyException, PrivacyRepository, PrivacyService, RetentionHoldRecord};
use EventFlow\Application\Security\SecureRandom;
use EventFlow\Application\Transaction\{TransactionManager, TransactionOptions};
use PHPUnit\Framework\TestCase;

final class PrivacyServiceTest extends TestCase
{
    public function testRequestCommitsVersionedPrivacyJobAndSafeAudit(): void
    {
        $fixture = new PrivacyFixture();
        $outcome = $fixture->service->request($fixture->principal, $fixture->scope, 44, 'retention-2026.1', 'Verified erasure request', 'privacy-key-001');

        self::assertSame(202, $outcome->reference->responseStatusCode);
        self::assertSame([Capability::MANAGE_PRIVACY], $fixture->jobs->record?->committedCapabilities);
        self::assertSame('retention-2026.1', $fixture->jobs->record?->payload['policy_version']);
        self::assertSame('retention-2026.1', $fixture->audit->records[0]->after['policy_version']);
        self::assertSame(hash('sha256', 'Verified erasure request'), $fixture->audit->records[0]->after['purpose_digest']);
        self::assertNull($fixture->audit->records[0]->reason);
    }

    public function testExecutionMinimizesForwardDeletesArtifactsOutsideTransactionAndCompletes(): void
    {
        $fixture = new PrivacyFixture();
        $fixture->service->request($fixture->principal, $fixture->scope, 44, 'retention-2026.1', 'Verified erasure request', 'privacy-key-002');
        $result = $fixture->service->execute($fixture->jobs->record);

        self::assertSame('completed', $result->status);
        self::assertSame(1, $fixture->repository->revocations);
        self::assertSame(1, $fixture->repository->minimizations);
        self::assertSame(1, $fixture->repository->tombstones);
        self::assertSame(['event-10/export-7-' . str_repeat('a', 32) . '.csv'], $fixture->storage->deleted);
        self::assertFalse($fixture->storage->transactionWasActive);
        self::assertTrue($fixture->service->isReconciled());
    }

    public function testFailureResumesFromDurableCheckpointWithoutRestoringOrRepeatingPiiSteps(): void
    {
        $fixture = new PrivacyFixture();
        $fixture->storage->failOnce = true;
        $fixture->service->request($fixture->principal, $fixture->scope, 44, 'retention-2026.1', 'Verified erasure request', 'privacy-key-003');

        try {
            $fixture->service->execute($fixture->jobs->record);
            self::fail('Expected artifact deletion failure.');
        } catch (RuntimeException) {
            self::assertSame('exports_invalidated', $fixture->repository->action?->checkpoint);
            self::assertSame('failed', $fixture->repository->action?->status);
        }

        $completed = $fixture->service->execute($fixture->jobs->record);
        self::assertSame('completed', $completed->status);
        self::assertSame(1, $fixture->repository->revocations);
        self::assertSame(1, $fixture->repository->minimizations);
        self::assertSame(1, $fixture->repository->tombstones);
    }

    public function testActiveHoldBlocksPrivacyActionBeforeIrreversibleWork(): void
    {
        $fixture = new PrivacyFixture();
        $fixture->repository->held = true;
        $this->expectException(PrivacyException::class);
        try {
            $fixture->service->request($fixture->principal, $fixture->scope, 44, 'retention-2026.1', 'Verified erasure request', 'privacy-key-004');
        } finally {
            self::assertNull($fixture->jobs->record);
            self::assertSame(0, $fixture->repository->revocations);
        }
    }

    public function testRoutineRetentionIsInternalSystemWorkWithVersionedPolicy(): void
    {
        $fixture = new PrivacyFixture();
        $action = $fixture->service->scheduleRetention($fixture->scope, 44, 'retention-2026.1', 'Policy age threshold reached');

        self::assertSame('retention', $action->requestKind);
        self::assertSame('system', $fixture->audit->records[0]->actorType);
        self::assertSame([Capability::MANAGE_PRIVACY], $fixture->jobs->record?->committedCapabilities);
    }

    public function testRetentionHoldsAreExplicitIdempotentAuditedResources(): void
    {
        $fixture = new PrivacyFixture();
        $placed = $fixture->service->placeHold($fixture->principal, $fixture->scope, 44, 'legal-2026.1', 'Litigation preservation', 'hold-key-001');
        self::assertSame('active', $placed->response->status);

        $released = $fixture->service->releaseHold($fixture->principal, $fixture->scope, 1, 'hold-key-002');
        self::assertSame('released', $released->response->status);
        self::assertCount(2, $fixture->audit->records);
    }

    public function testPostRestoreGateStaysClosedUntilTombstoneIsReappliedForward(): void
    {
        $fixture = new PrivacyFixture();
        $fixture->service->request($fixture->principal, $fixture->scope, 44, 'retention-2026.1', 'Verified erasure request', 'privacy-key-005');
        $fixture->service->execute($fixture->jobs->record);

        self::assertSame(1, $fixture->service->requirePostRestoreReconciliation());
        self::assertFalse($fixture->service->isReconciled());
        self::assertSame(1, $fixture->service->reconcileRestoredState());
        self::assertTrue($fixture->service->isReconciled());
        self::assertSame(2, $fixture->repository->minimizations);
    }
}

final class PrivacyFixture
{
    public EventScope $scope;
    public PrincipalContext $principal;
    public PrivacyMemoryRepository $repository;
    public PrivacyMemoryJobs $jobs;
    public PrivacyMemoryStorage $storage;
    public PrivacyMemoryAudit $audit;
    public PrivacyService $service;

    public function __construct()
    {
        $this->scope = new EventScope(10);
        $this->principal = PrincipalContext::wordpressUser(7);
        $clock = new PrivacyClock();
        $transactions = new PrivacyTransactions();
        $this->repository = new PrivacyMemoryRepository();
        $this->jobs = new PrivacyMemoryJobs();
        $this->storage = new PrivacyMemoryStorage($transactions);
        $this->audit = new PrivacyMemoryAudit();
        $authorization = new AuthorizationService(new PrivacyMemberships(), new RoleCapabilityPolicy(), $clock, new PrivacyNoRecovery());
        $idempotency = new IdempotencyService(new PrivacyMemoryIdempotency(), $transactions, $clock, new PrivacyRandom(), new CanonicalRequestHasher());
        $audit = new AuditService($this->audit, $transactions, $clock, new AuditPayloadRedactor(), new AuditCanonicalizer());
        $this->service = new PrivacyService($this->repository, $this->storage, $this->jobs, $authorization, $idempotency, $audit, $clock, $transactions);
    }
}

final class PrivacyMemoryRepository implements PrivacyRepository
{
    public ?PrivacyActionRecord $action = null;
    public bool $held = false;
    public int $revocations = 0;
    public int $minimizations = 0;
    public int $tombstones = 0;
    public bool $reconciliationRequired = false;
    private string $locator = 'event-10/export-7-aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa.csv';

    public function createAction(EventScope $s, int $i, string $k, string $v, string $p, ?int $a, DateTimeImmutable $n): PrivacyActionRecord { if ($this->held) throw new PrivacyException('retention_hold_active'); return $this->action = new PrivacyActionRecord(1, $s, $i, $k, $v, $p, 'pending', 'requested'); }
    public function resume(EventScope $s, int $id, DateTimeImmutable $n): PrivacyActionRecord { if ($this->held) throw new PrivacyException('retention_hold_active'); $a = $this->action ?? throw new PrivacyException('resource_not_found'); return $this->action = new PrivacyActionRecord($id, $s, $a->invitationId, $a->requestKind, $a->policyVersion, $a->purpose, $a->status === 'completed' ? 'completed' : 'processing', $a->checkpoint); }
    public function advance(PrivacyActionRecord $a, string $c, DateTimeImmutable $n): PrivacyActionRecord { return $this->action = new PrivacyActionRecord($a->privacyActionId, $a->eventScope, $a->invitationId, $a->requestKind, $a->policyVersion, $a->purpose, 'processing', $c); }
    public function fail(PrivacyActionRecord $a, string $c, DateTimeImmutable $n): void { $this->action = new PrivacyActionRecord($a->privacyActionId, $a->eventScope, $a->invitationId, $a->requestKind, $a->policyVersion, $a->purpose, 'failed', $a->checkpoint); }
    public function revokeCredentials(PrivacyActionRecord $a, DateTimeImmutable $n): void { $this->revocations++; }
    public function minimizePii(PrivacyActionRecord $a, DateTimeImmutable $n): void { $this->minimizations++; }
    public function invalidatePiiExports(PrivacyActionRecord $a, DateTimeImmutable $n): array { return [$this->locator]; }
    public function invalidatedArtifactLocators(PrivacyActionRecord $a): array { return [$this->locator]; }
    public function recordTombstone(PrivacyActionRecord $a, DateTimeImmutable $n): void { $this->tombstones++; $this->reconciliationRequired = false; }
    public function complete(PrivacyActionRecord $a, DateTimeImmutable $n): PrivacyActionRecord { return $this->action = new PrivacyActionRecord($a->privacyActionId, $a->eventScope, $a->invitationId, $a->requestKind, $a->policyVersion, $a->purpose, 'completed', 'completed'); }
    public function placeHold(EventScope $s, ?int $i, string $v, string $r, int $u, DateTimeImmutable $n): RetentionHoldRecord { $this->held = true; return new RetentionHoldRecord(1, $s, $i, $v, $r, 'active'); }
    public function releaseHold(EventScope $s, int $id, int $u, DateTimeImmutable $n): RetentionHoldRecord { $this->held = false; return new RetentionHoldRecord($id, $s, null, 'v1', 'Legal hold', 'released'); }
    public function requireReconciliation(DateTimeImmutable $n): int { $this->reconciliationRequired = $this->action !== null; return $this->reconciliationRequired ? 1 : 0; }
    public function pendingReconciliation(): array { return $this->reconciliationRequired && $this->action !== null ? [$this->action] : []; }
    public function isReconciled(): bool { return !$this->reconciliationRequired; }
}

final class PrivacyMemoryStorage implements ExportArtifactStorage
{
    public bool $failOnce = false;
    public bool $transactionWasActive = true;
    public array $deleted = [];
    public function __construct(private PrivacyTransactions $transactions) {}
    public function publish(ExportRecord $export, iterable $rows): PublishedExportArtifact { throw new RuntimeException('unused'); }
    public function delete(string $locator): void { $this->transactionWasActive = $this->transactions->isActive(); if ($this->failOnce) { $this->failOnce = false; throw new RuntimeException('storage failure'); } $this->deleted[] = $locator; }
}

final class PrivacyMemoryJobs implements JobRepository
{
    public ?JobRecord $record = null;
    public function enqueue(JobRequest $r, DateTimeImmutable $at): JobRecord { return $this->record = new JobRecord(9, $r->eventScope, $r->jobType, $r->payloadVersion, $r->payload, $r->committedCapabilities, JobStatus::PENDING, $r->priority, 0, $r->maxAttempts); }
    public function claimNext(string $o, string $t, DateTimeImmutable $n, DateTimeImmutable $e): ?JobRecord { return null; }
    public function heartbeat(int $i, string $t, DateTimeImmutable $n, DateTimeImmutable $e): void {}
    public function complete(int $i, string $t, DateTimeImmutable $n): void {}
    public function fail(int $i, string $t, string $e, bool $d, DateTimeImmutable $f, DateTimeImmutable $a): void {}
    public function reconcile(DateTimeImmutable $n): JobReconciliationResult { return new JobReconciliationResult(0, 0, false); }
}

final class PrivacyTransactions implements TransactionManager { private int $depth = 0; public function transactional(callable $o, ?TransactionOptions $x = null): mixed { $this->depth++; try { return $o(); } finally { $this->depth--; } } public function isActive(): bool { return $this->depth > 0; } public function assertNotActive(): void { if ($this->isActive()) throw new RuntimeException('transaction active'); } }
final readonly class PrivacyClock implements Clock { public function now(): DateTimeImmutable { return new DateTimeImmutable('2026-08-17 12:00:00', new DateTimeZone('UTC')); } }
final readonly class PrivacyRandom implements SecureRandom { public function hex(int $bytes): string { return str_repeat('f', $bytes * 2); } }
final class PrivacyMemberships implements MembershipReader { public function findCurrent(EventScope $s, int $u): ?MembershipSnapshot { return new MembershipSnapshot(1, $s, $u, EventRole::OWNER, true, null); } }
final readonly class PrivacyNoRecovery implements GlobalRecoveryAuthority { public function canRecoverPrimaryOwnership(int $u): bool { return false; } }
final class PrivacyMemoryAudit implements AuditRepository { public array $records = []; public function lockChainHead(?EventScope $s): ?string { return $this->records === [] ? null : $this->records[array_key_last($this->records)]->recordHash; } public function append(AuditRecord $r): int { $this->records[] = $r; return count($this->records); } }
final class PrivacyMemoryIdempotency implements IdempotencyRepository
{
    public function claim(IdempotencyRequest $q, string $l, DateTimeImmutable $n, DateTimeImmutable $le, DateTimeImmutable $re): IdempotencyClaimResult { return new IdempotencyClaimResult(IdempotencyClaimState::ACQUIRED, new IdempotencyRecord(1, $q->requestFingerprint, 'in_progress', $le, null, false)); }
    public function complete(int $id, string $l, IdempotencyResultReference $r, bool $s, DateTimeImmutable $at): void {}
    public function fail(int $id, string $l, DateTimeImmutable $at): void {}
}
