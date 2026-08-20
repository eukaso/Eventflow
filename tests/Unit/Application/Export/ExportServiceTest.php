<?php

namespace EventFlow\Tests\Unit\Application\Export;

use DateTimeImmutable;
use DateTimeZone;
use EventFlow\Application\Audit\{AuditCanonicalizer, AuditPayloadRedactor, AuditRecord, AuditRepository, AuditService};
use EventFlow\Application\Authorization\{AuthorizationException, AuthorizationService, Capability, EventRole, GlobalRecoveryAuthority, MembershipReader, MembershipSnapshot, PrincipalContext, RoleCapabilityPolicy};
use EventFlow\Application\Clock\Clock;
use EventFlow\Application\Export\{ExportArtifactStorage, ExportDataSource, ExportException, ExportFormat, ExportRecord, ExportRepository, ExportService, ExportType, PublishedExportArtifact};
use EventFlow\Application\Idempotency\{CanonicalRequestHasher, IdempotencyClaimResult, IdempotencyClaimState, IdempotencyRecord, IdempotencyRepository, IdempotencyRequest, IdempotencyResultReference, IdempotencyService};
use EventFlow\Application\Job\{JobRecord, JobReconciliationResult, JobRepository, JobRequest, JobStatus};
use EventFlow\Application\Persistence\EventScope;
use EventFlow\Application\Security\SecureRandom;
use EventFlow\Application\Transaction\{TransactionManager, TransactionOptions};
use PHPUnit\Framework\TestCase;

final class ExportServiceTest extends TestCase
{
    public function testExportRecordFailsClosedOnPiiClassificationMismatch(): void
    {
        $this->expectException(ExportException::class);
        $now = new DateTimeImmutable('2026-08-16T12:00:00Z');
        new ExportRecord(1, new EventScope(10), ExportType::ATTENDEES, ExportFormat::CSV, false, 'Operations', $now, 'pending', $now->modify('+1 day'));
    }

    public function testPiiRequestRequiresPurposeAndCommitsExportJobAuthority(): void
    {
        $fixture = new ExportFixture();
        $this->expectException(ExportException::class);
        try {
            $fixture->service->request($fixture->principal, $fixture->scope, ExportType::ATTENDEES, ExportFormat::CSV, '', 'export-key-001');
        } finally {
            self::assertNull($fixture->jobs->record);
        }
    }

    public function testRequestGenerateAndDownloadPreserveSecurityBoundaries(): void
    {
        $fixture = new ExportFixture();
        $outcome = $fixture->service->request($fixture->principal, $fixture->scope, ExportType::ATTENDEES, ExportFormat::CSV, 'Door operations', 'export-key-002');

        self::assertSame(202, $outcome->reference->responseStatusCode);
        self::assertSame([Capability::EXPORT_PII], $fixture->jobs->record?->committedCapabilities);
        self::assertSame('2026-08-16T12:00:00+00:00', $fixture->jobs->record?->payload['cutoff_at']);

        $ready = $fixture->service->generate($fixture->jobs->record);
        self::assertSame('ready', $ready->status);
        self::assertFalse($fixture->storage->transactionWasActive);

        $grant = $fixture->service->authorizeDownload($fixture->principal, $fixture->scope, $ready->exportId);
        self::assertSame('private/export.csv', $grant->locator);
        self::assertSame(0, $fixture->repository->downloads);
        $fixture->service->recordDownload($fixture->principal, $fixture->scope, $ready->exportId);
        self::assertSame(1, $fixture->repository->downloads);
        self::assertGreaterThanOrEqual(3, count($fixture->audit->records));
    }

    public function testDownloadReauthorizesAgainstCurrentMembership(): void
    {
        $fixture = new ExportFixture();
        $fixture->service->request($fixture->principal, $fixture->scope, ExportType::ATTENDEES, ExportFormat::CSV, 'Door operations', 'export-key-003');
        $ready = $fixture->service->generate($fixture->jobs->record);
        $fixture->memberships->enabled = false;

        $this->expectException(AuthorizationException::class);
        try {
            $fixture->service->authorizeDownload($fixture->principal, $fixture->scope, $ready->exportId);
        } finally {
            self::assertSame(0, $fixture->repository->downloads);
        }
    }
}

final class ExportFixture
{
    public EventScope $scope;
    public PrincipalContext $principal;
    public ExportMemoryRepository $repository;
    public ExportMemoryJobs $jobs;
    public ExportMemoryStorage $storage;
    public ExportMemoryAudit $audit;
    public ExportMemberships $memberships;
    public ExportService $service;

    public function __construct()
    {
        $this->scope = new EventScope(10);
        $this->principal = PrincipalContext::wordpressUser(7);
        $clock = new ExportClock();
        $transactions = new ExportTransactions();
        $this->repository = new ExportMemoryRepository();
        $this->jobs = new ExportMemoryJobs();
        $this->storage = new ExportMemoryStorage($transactions);
        $this->audit = new ExportMemoryAudit();
        $this->memberships = new ExportMemberships();
        $authorization = new AuthorizationService($this->memberships, new RoleCapabilityPolicy(), $clock, new ExportNoRecovery());
        $idempotency = new IdempotencyService(new ExportMemoryIdempotency(), $transactions, $clock, new ExportRandom(), new CanonicalRequestHasher());
        $audit = new AuditService($this->audit, $transactions, $clock, new AuditPayloadRedactor(), new AuditCanonicalizer());
        $this->service = new ExportService($this->repository, new ExportRows(), $this->storage, $this->jobs, $authorization, $idempotency, $audit, $clock, $transactions);
    }
}

final class ExportMemoryRepository implements ExportRepository
{
    public ?ExportRecord $record = null;
    public int $downloads = 0;
    public function create(EventScope $s, ExportType $t, ExportFormat $f, string $p, DateTimeImmutable $c, DateTimeImmutable $e, ?int $a, DateTimeImmutable $n): ExportRecord { return $this->record = new ExportRecord(1, $s, $t, $f, $t->containsPii(), $p, $c, 'pending', $e); }
    public function lock(EventScope $s, int $id): ?ExportRecord { return $this->record; }
    public function beginGeneration(EventScope $s, int $id, int $max, DateTimeImmutable $n): ExportRecord { $r = $this->record ?? throw new ExportException('resource_not_found'); return $this->record = new ExportRecord($id, $s, $r->type, $r->format, $r->containsPii, $r->purpose, $r->cutoffAt, 'generating', $r->expiresAt); }
    public function markReady(ExportRecord $e, PublishedExportArtifact $a, DateTimeImmutable $n): ExportRecord { return $this->record = new ExportRecord($e->exportId, $e->eventScope, $e->type, $e->format, $e->containsPii, $e->purpose, $e->cutoffAt, 'ready', $e->expiresAt, $a->locator, $a->sha256, $a->mimeType, $a->sizeBytes); }
    public function markFailed(ExportRecord $e, string $c, DateTimeImmutable $n): void {}
    public function recordDownload(ExportRecord $e, DateTimeImmutable $n): void { $this->downloads++; }
}

final class ExportMemoryStorage implements ExportArtifactStorage
{
    public bool $transactionWasActive = true;
    public function __construct(private ExportTransactions $transactions) {}
    public function publish(ExportRecord $e, iterable $rows): PublishedExportArtifact { $this->transactionWasActive = $this->transactions->isActive(); iterator_to_array($rows); return new PublishedExportArtifact('private/export.csv', str_repeat('a', 64), 'text/csv', 12); }
    public function delete(string $locator): void {}
}

final class ExportRows implements ExportDataSource { public function rows(ExportRecord $e): iterable { yield ['id' => 1]; } }
final class ExportMemoryJobs implements JobRepository
{
    public ?JobRecord $record = null;
    public function enqueue(JobRequest $r, DateTimeImmutable $at): JobRecord { return $this->record = new JobRecord(9, $r->eventScope, $r->jobType, $r->payloadVersion, $r->payload, $r->committedCapabilities, JobStatus::PENDING, $r->priority, 0, $r->maxAttempts); }
    public function claimNext(string $o, string $t, DateTimeImmutable $n, DateTimeImmutable $e): ?JobRecord { return null; }
    public function heartbeat(int $i, string $t, DateTimeImmutable $n, DateTimeImmutable $e): void {}
    public function complete(int $i, string $t, DateTimeImmutable $n): void {}
    public function fail(int $i, string $t, string $e, bool $d, DateTimeImmutable $f, DateTimeImmutable $a): void {}
    public function reconcile(DateTimeImmutable $n): JobReconciliationResult { return new JobReconciliationResult(0, 0, false); }
}

final class ExportTransactions implements TransactionManager { private int $depth = 0; public function transactional(callable $o, ?TransactionOptions $x = null): mixed { $this->depth++; try { return $o(); } finally { $this->depth--; } } public function isActive(): bool { return $this->depth > 0; } public function assertNotActive(): void {} }
final readonly class ExportClock implements Clock { public function now(): DateTimeImmutable { return new DateTimeImmutable('2026-08-16 12:00:00', new DateTimeZone('UTC')); } }
final readonly class ExportRandom implements SecureRandom { public function hex(int $bytes): string { return str_repeat('e', $bytes * 2); } }
final class ExportMemberships implements MembershipReader { public bool $enabled = true; public function findCurrent(EventScope $s, int $u): ?MembershipSnapshot { return $this->enabled ? new MembershipSnapshot(1, $s, $u, EventRole::OWNER, false, null) : null; } }
final readonly class ExportNoRecovery implements GlobalRecoveryAuthority { public function canRecoverPrimaryOwnership(int $u): bool { return false; } }
final class ExportMemoryAudit implements AuditRepository { public array $records = []; public function lockChainHead(?EventScope $s): ?string { return $this->records === [] ? null : $this->records[array_key_last($this->records)]->recordHash; } public function append(AuditRecord $r): int { $this->records[] = $r; return count($this->records); } }
final class ExportMemoryIdempotency implements IdempotencyRepository
{
    private array $records = [];
    public function claim(IdempotencyRequest $q, string $l, DateTimeImmutable $n, DateTimeImmutable $le, DateTimeImmutable $re): IdempotencyClaimResult { return new IdempotencyClaimResult(IdempotencyClaimState::ACQUIRED, $this->records[] = new IdempotencyRecord(count($this->records) + 1, $q->requestFingerprint, 'in_progress', $le, null, false)); }
    public function complete(int $id, string $l, IdempotencyResultReference $r, bool $s, DateTimeImmutable $at): void {}
    public function fail(int $id, string $l, DateTimeImmutable $at): void {}
}
