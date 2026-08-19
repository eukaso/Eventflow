<?php

namespace EventFlow\Tests\Unit\Application\Event;

use DateTimeImmutable;
use DateTimeZone;
use EventFlow\Application\Audit\{AuditAction, AuditCanonicalizer, AuditPayloadRedactor, AuditRecord, AuditRepository, AuditService};
use EventFlow\Application\Authorization\{AuthorizationService, EventRole, GlobalRecoveryAuthority, MembershipReader, MembershipSnapshot, PrincipalContext, RoleCapabilityPolicy};
use EventFlow\Application\Clock\Clock;
use EventFlow\Application\Event\{CreateEvent, EventAccessService, EventActivationReadiness, EventDraftPatch, EventLifecycleException, EventLifecycleRepository, EventPage, EventQueryRepository, EventRecord, EventStatus};
use EventFlow\Application\Idempotency\{CanonicalRequestHasher, IdempotencyClaimResult, IdempotencyClaimState, IdempotencyRecord, IdempotencyRepository, IdempotencyRequest, IdempotencyResultReference, IdempotencyService};
use EventFlow\Application\Persistence\EventScope;
use EventFlow\Application\Security\SecureRandom;
use EventFlow\Application\Transaction\{TransactionManager, TransactionOptions};
use PHPUnit\Framework\TestCase;

final class EventAccessServiceTest extends TestCase
{
    public function testListIsBoundedAndDelegatesOnlyForAuthenticatedWordPressUser(): void
    {
        $fixture = new AccessFixture();
        $page = $fixture->service->listAccessible($fixture->principal, 25, 10);

        self::assertSame([51], array_map(fn (EventRecord $event): int => $event->scope->eventId, $page->events));
        self::assertSame([7, 25, 10], $fixture->queries->lastArguments);

        $this->expectException(EventLifecycleException::class);
        $fixture->service->listAccessible(PrincipalContext::anonymous());
    }

    public function testReadRequiresCurrentViewCapability(): void
    {
        $fixture = new AccessFixture();
        self::assertSame(51, $fixture->service->read($fixture->principal, new EventScope(51))->scope->eventId);

        $this->expectException(\EventFlow\Application\Authorization\AuthorizationException::class);
        $fixture->service->read(PrincipalContext::wordpressUser(8), new EventScope(51));
    }

    public function testDraftUpdateIsRevisionGuardedAuditedAndIdempotent(): void
    {
        $fixture = new AccessFixture();
        $patch = new EventDraftPatch([
            'name' => 'Updated Event',
            'starts_at' => null,
            'venue_id' => 12,
        ], 3);

        $outcome = $fixture->service->updateDraft($fixture->principal, new EventScope(51), $patch, 'event-update-0001');
        $replay = $fixture->service->updateDraft($fixture->principal, new EventScope(51), $patch, 'event-update-0001');

        self::assertFalse($outcome->replayed);
        self::assertTrue($replay->replayed);
        self::assertSame('Updated Event', $outcome->response->name);
        self::assertNull($outcome->response->startsAt);
        self::assertSame(12, $outcome->response->venueId);
        self::assertSame(4, $outcome->response->revision);
        self::assertSame([AuditAction::EVENT_UPDATED], $fixture->audit->actions);
        self::assertSame(1, $fixture->events->updates);
    }

    public function testDraftUpdateRejectsStaleRevisionBeforeWrite(): void
    {
        $fixture = new AccessFixture();

        try {
            $fixture->service->updateDraft(
                $fixture->principal,
                new EventScope(51),
                new EventDraftPatch(['name' => 'Stale'], 2),
                'event-update-0002',
            );
            self::fail('Expected stale revision failure.');
        } catch (EventLifecycleException $exception) {
            self::assertSame('resource_modified', $exception->safeCode);
        }
        self::assertSame(0, $fixture->events->updates);
        self::assertSame([], $fixture->audit->actions);
    }

    public function testDraftUpdateRejectsLifecycleMutationOutsideDraft(): void
    {
        $fixture = new AccessFixture(EventStatus::ACTIVE);

        try {
            $fixture->service->updateDraft(
                $fixture->principal,
                new EventScope(51),
                new EventDraftPatch(['name' => 'Not Allowed'], 3),
                'event-update-0003',
            );
            self::fail('Expected lifecycle failure.');
        } catch (EventLifecycleException $exception) {
            self::assertSame('event_transition_invalid', $exception->safeCode);
        }
        self::assertSame(0, $fixture->events->updates);
    }
}

final class AccessFixture
{
    public readonly EventAccessService $service;
    public readonly AccessEventRepository $events;
    public readonly AccessQueryRepository $queries;
    public readonly AccessAuditRepository $audit;
    public readonly PrincipalContext $principal;

    public function __construct(EventStatus $status = EventStatus::DRAFT)
    {
        $clock = new AccessClock();
        $transactions = new AccessTransactions();
        $this->events = new AccessEventRepository($status);
        $this->queries = new AccessQueryRepository($this->events->record);
        $this->audit = new AccessAuditRepository();
        $authorization = new AuthorizationService(new AccessMembershipReader(), new RoleCapabilityPolicy(), $clock, new AccessNoRecovery());
        $idempotency = new IdempotencyService(new AccessIdempotencyRepository(), $transactions, $clock, new AccessRandom(), new CanonicalRequestHasher());
        $audit = new AuditService($this->audit, $transactions, $clock, new AuditPayloadRedactor(), new AuditCanonicalizer());
        $this->service = new EventAccessService($this->events, $this->queries, $authorization, $idempotency, $audit, $clock);
        $this->principal = PrincipalContext::wordpressUser(7);
    }
}

final class AccessEventRepository implements EventLifecycleRepository
{
    public EventRecord $record;
    public int $updates = 0;

    public function __construct(EventStatus $status)
    {
        $this->record = new EventRecord(new EventScope(51), 'Event', 'event', $status, 'UTC', new DateTimeImmutable('2026-09-01T18:00:00Z'), new DateTimeImmutable('2026-09-02T01:00:00Z'), 8, 3);
    }
    public function createDraft(CreateEvent $event, int $primaryOwnerUserId, DateTimeImmutable $now): EventRecord { return $this->record; }
    public function find(EventScope $scope): ?EventRecord { return $scope->eventId === 51 ? $this->record : null; }
    public function lock(EventScope $scope): ?EventRecord { return $this->find($scope); }
    public function activationReadiness(EventRecord $event): EventActivationReadiness { return new EventActivationReadiness([]); }
    public function captureVenueSnapshot(EventRecord $event, ?int $actorUserId, DateTimeImmutable $now): void {}
    public function transition(EventRecord $event, EventStatus $target, ?int $actorUserId, DateTimeImmutable $now): EventRecord { return $event; }
    public function updateDraft(EventRecord $current, CreateEvent $replacement, ?int $actorUserId, DateTimeImmutable $now): EventRecord
    {
        $this->updates++;
        return $this->record = new EventRecord($current->scope, $replacement->name, $replacement->slug, EventStatus::DRAFT, $replacement->timezone, $replacement->startsAt, $replacement->endsAt, $replacement->venueId, $current->revision + 1);
    }
}

final class AccessQueryRepository implements EventQueryRepository
{
    /** @var list<int|null> */ public array $lastArguments = [];
    public function __construct(private EventRecord $record) {}
    public function listAccessibleForUser(int $userId, DateTimeImmutable $now, int $limit, ?int $afterEventId): EventPage
    {
        $this->lastArguments = [$userId, $limit, $afterEventId];
        return new EventPage([$this->record], null);
    }
}

final class AccessMembershipReader implements MembershipReader
{
    public function findCurrent(EventScope $eventScope, int $userId): ?MembershipSnapshot
    {
        return $userId === 7 ? new MembershipSnapshot(1, $eventScope, 7, EventRole::OWNER, true, null) : null;
    }
}
final class AccessNoRecovery implements GlobalRecoveryAuthority { public function canRecoverPrimaryOwnership(int $userId): bool { return false; } }
final class AccessClock implements Clock { public function now(): DateTimeImmutable { return new DateTimeImmutable('2026-08-18T18:00:00Z'); } }
final class AccessRandom implements SecureRandom { public function hex(int $bytes): string { return str_repeat('ab', $bytes); } }
final class AccessTransactions implements TransactionManager
{
    private int $depth = 0;
    public function transactional(callable $operation, ?TransactionOptions $options = null): mixed { $this->depth++; try { return $operation(); } finally { $this->depth--; } }
    public function isActive(): bool { return $this->depth > 0; }
    public function assertNotActive(): void { if ($this->isActive()) throw new \RuntimeException('transaction_active'); }
}
final class AccessIdempotencyRepository implements IdempotencyRepository
{
    private ?IdempotencyResultReference $completed = null;
    private ?string $fingerprint = null;

    public function claim(IdempotencyRequest $request, string $leaseToken, DateTimeImmutable $now, DateTimeImmutable $leaseExpiresAt, DateTimeImmutable $recordExpiresAt): IdempotencyClaimResult
    {
        if ($this->completed !== null && $this->fingerprint === $request->requestFingerprint) {
            return new IdempotencyClaimResult(IdempotencyClaimState::REPLAY, new IdempotencyRecord(1, $request->requestFingerprint, 'completed', null, $this->completed, false));
        }
        $this->fingerprint = $request->requestFingerprint;
        return new IdempotencyClaimResult(IdempotencyClaimState::ACQUIRED, new IdempotencyRecord(1, $request->requestFingerprint, 'in_progress', $leaseExpiresAt, null, false));
    }
    public function complete(int $recordId, string $leaseToken, IdempotencyResultReference $reference, bool $sensitiveResult, DateTimeImmutable $completedAt): void { $this->completed = $reference; }
    public function fail(int $recordId, string $leaseToken, DateTimeImmutable $failedAt): void {}
}
final class AccessAuditRepository implements AuditRepository
{
    /** @var list<AuditAction> */ public array $actions = [];
    public function lockChainHead(?EventScope $eventScope): ?string { return null; }
    public function append(AuditRecord $record): int { $this->actions[] = $record->action; return count($this->actions); }
}
