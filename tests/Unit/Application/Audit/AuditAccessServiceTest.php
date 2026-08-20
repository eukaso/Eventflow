<?php

namespace EventFlow\Tests\Unit\Application\Audit;

use DateTimeImmutable;
use DateTimeZone;
use EventFlow\Application\Audit\{AuditAccessRepository, AuditAccessService, AuditAction, AuditCanonicalizer, AuditChainSnapshot, AuditChainVerifier, AuditEntityType, AuditEntry, AuditEntryPage, AuditEntrySummary, AuditException, AuditRecord, AuditSource};
use EventFlow\Application\Authorization\{AuthorizationException, AuthorizationService, EventRole, GlobalRecoveryAuthority, MembershipReader, MembershipSnapshot, PrincipalContext, RoleCapabilityPolicy};
use EventFlow\Application\Clock\Clock;
use EventFlow\Application\Persistence\EventScope;
use PHPUnit\Framework\TestCase;

final class AuditAccessServiceTest extends TestCase
{
    public function testOrganizerCanListWithBoundedTypedFiltersAndReadDetail(): void
    {
        $repository = new AuditAccessMemoryRepository();
        $service = $this->service($repository, EventRole::ORGANIZER);
        $scope = new EventScope(9);
        $principal = PrincipalContext::wordpressUser(7);

        $page = $service->list(
            $principal, $scope, 25, 10, 'event.updated', 'event', 9, 7, 'rest_api',
            new DateTimeImmutable('2026-08-18T00:00:00Z'),
            new DateTimeImmutable('2026-08-19T00:00:00Z'),
        );

        self::assertSame(41, $page->entries[0]->auditLogId);
        self::assertSame(AuditAction::EVENT_UPDATED, $repository->query[2]);
        self::assertSame(AuditEntityType::EVENT, $repository->query[3]);
        self::assertSame(AuditSource::REST_API, $repository->query[6]);
        self::assertSame(41, $service->read($principal, $scope, 41)->auditLogId);
    }

    public function testRoleWithoutAuditCapabilityIsDenied(): void
    {
        $this->expectException(AuthorizationException::class);
        $this->service(new AuditAccessMemoryRepository(), EventRole::REPORTING)
            ->list(PrincipalContext::wordpressUser(7), new EventScope(9));
    }

    public function testInvalidFilterFailsBeforeRepositoryAccess(): void
    {
        $repository = new AuditAccessMemoryRepository();
        $service = $this->service($repository, EventRole::OWNER);

        $this->expectException(AuditException::class);
        try {
            $service->list(PrincipalContext::wordpressUser(7), new EventScope(9), action: 'unknown');
        } finally {
            self::assertSame([], $repository->query);
        }
    }

    public function testIntegrityReportMakesTamperingVisibleWithoutExposingPayload(): void
    {
        $repository = new AuditAccessMemoryRepository(tampered: true);
        $report = $this->service($repository, EventRole::OWNER)->verifyIntegrity(
            PrincipalContext::wordpressUser(7), new EventScope(9),
        );

        self::assertFalse($report->valid);
        self::assertSame('audit_record_hash_invalid', $report->failureCode);
        self::assertSame(1, $report->recordCount);
    }

    private function service(AuditAccessRepository $repository, EventRole $role): AuditAccessService
    {
        $canonicalizer = new AuditCanonicalizer();
        return new AuditAccessService(
            $repository,
            new AuthorizationService(
                new AuditAccessMemberships($role), new RoleCapabilityPolicy(),
                new AuditAccessClock(), new AuditAccessRecovery(),
            ),
            new AuditChainVerifier($canonicalizer),
        );
    }
}

final class AuditAccessMemoryRepository implements AuditAccessRepository
{
    /** @var list<mixed> */ public array $query = [];
    public function __construct(private readonly bool $tampered = false) {}

    public function listEntries(EventScope $scope, int $limit, ?int $afterAuditLogId, ?AuditAction $action, ?AuditEntityType $entityType, ?int $entityId, ?int $actorUserId, ?AuditSource $source, ?DateTimeImmutable $occurredFrom, ?DateTimeImmutable $occurredUntil): AuditEntryPage
    {
        $this->query = [$limit, $afterAuditLogId, $action, $entityType, $entityId, $actorUserId, $source, $occurredFrom, $occurredUntil];
        $record = $this->record();
        return new AuditEntryPage([new AuditEntrySummary(41, $scope, 'user', 7, $record->action, $record->entityType, 9, 'Updated', $record->source, $record->occurredAt, $record->recordHash)], null);
    }

    public function findEntry(EventScope $scope, int $auditLogId): ?AuditEntry
    {
        return $auditLogId === 41 ? new AuditEntry(41, $this->record()) : null;
    }

    public function chainSnapshot(EventScope $scope, int $maximumRecords): AuditChainSnapshot
    {
        $record = $this->record();
        if ($this->tampered) $record = $record->withHash(str_repeat('0', 64));
        return new AuditChainSnapshot([$record], 41, $record->recordHash);
    }

    private function record(): AuditRecord
    {
        $at = new DateTimeImmutable('2026-08-19T12:00:00Z');
        $record = new AuditRecord(new EventScope(9), 'user', 7, null, AuditAction::EVENT_UPDATED, AuditEntityType::EVENT, 9, null, null, 'Updated', ['name' => 'Old'], ['name' => 'New'], AuditSource::REST_API, null, $at, $at, 1, 1, null, '');
        return $record->withHash((new AuditCanonicalizer())->hash($record));
    }
}

final readonly class AuditAccessMemberships implements MembershipReader
{
    public function __construct(private EventRole $role) {}
    public function findCurrent(EventScope $scope, int $userId): ?MembershipSnapshot { return new MembershipSnapshot(1, $scope, $userId, $this->role, false, null); }
}
final readonly class AuditAccessClock implements Clock { public function now(): DateTimeImmutable { return new DateTimeImmutable('2026-08-19 12:00:00', new DateTimeZone('UTC')); } }
final readonly class AuditAccessRecovery implements GlobalRecoveryAuthority { public function canRecoverPrimaryOwnership(int $userId): bool { return false; } }
