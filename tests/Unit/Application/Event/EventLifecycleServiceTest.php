<?php

namespace EventFlow\Tests\Unit\Application\Event;

use DateTimeImmutable;
use DateTimeZone;
use EventFlow\Application\Audit\AuditAction;
use EventFlow\Application\Audit\AuditCanonicalizer;
use EventFlow\Application\Audit\AuditPayloadRedactor;
use EventFlow\Application\Audit\AuditRecord;
use EventFlow\Application\Audit\AuditRepository;
use EventFlow\Application\Audit\AuditService;
use EventFlow\Application\Authorization\AuthorizationService;
use EventFlow\Application\Authorization\EventRole;
use EventFlow\Application\Authorization\GlobalRecoveryAuthority;
use EventFlow\Application\Authorization\MembershipReader;
use EventFlow\Application\Authorization\MembershipSnapshot;
use EventFlow\Application\Authorization\PrincipalContext;
use EventFlow\Application\Authorization\RoleCapabilityPolicy;
use EventFlow\Application\Clock\Clock;
use EventFlow\Application\Event\CreateEvent;
use EventFlow\Application\Event\EventActivationReadiness;
use EventFlow\Application\Event\EventCreationAuthority;
use EventFlow\Application\Event\EventLifecycleException;
use EventFlow\Application\Event\EventLifecycleRepository;
use EventFlow\Application\Event\EventLifecycleService;
use EventFlow\Application\Event\EventRecord;
use EventFlow\Application\Event\EventStatus;
use EventFlow\Application\Idempotency\CanonicalRequestHasher;
use EventFlow\Application\Idempotency\IdempotencyClaimResult;
use EventFlow\Application\Idempotency\IdempotencyClaimState;
use EventFlow\Application\Idempotency\IdempotencyRecord;
use EventFlow\Application\Idempotency\IdempotencyRepository;
use EventFlow\Application\Idempotency\IdempotencyRequest;
use EventFlow\Application\Idempotency\IdempotencyResultReference;
use EventFlow\Application\Idempotency\IdempotencyService;
use EventFlow\Application\Persistence\EventScope;
use EventFlow\Application\Security\SecureRandom;
use EventFlow\Application\Transaction\TransactionManager;
use EventFlow\Application\Transaction\TransactionOptions;
use PHPUnit\Framework\TestCase;

final class EventLifecycleServiceTest extends TestCase
{
    public function testCreationAtomicallyEstablishesDraftDefaultsOwnerAuditAndReplay(): void
    {
        $fixture = new EventLifecycleFixture();
        $command = $fixture->command();

        $first = $fixture->service->create($fixture->principal, $command, 'create-key-0001');
        $replay = $fixture->service->create($fixture->principal, $command, 'create-key-0001');

        self::assertFalse($first->replayed);
        self::assertTrue($replay->replayed);
        self::assertSame(EventStatus::DRAFT, $first->response->status);
        self::assertSame(1, $fixture->events->createdCount);
        self::assertTrue($fixture->events->defaultConfigurationCreated);
        self::assertTrue($fixture->events->primaryOwnerCreated);
        self::assertSame([AuditAction::EVENT_CREATED], $fixture->audit->actions());
    }

    public function testActivationRechecksReadinessUnderLockAndDoesNotMutateWhenBlocked(): void
    {
        $fixture = new EventLifecycleFixture();
        $created = $fixture->create();
        $fixture->events->blockers = ['event_configuration_required'];

        try {
            $fixture->service->activate($fixture->principal, $created->scope, 'activate-key-0001');
            self::fail('Expected activation readiness failure.');
        } catch (EventLifecycleException $exception) {
            self::assertSame('event_activation_not_ready', $exception->safeCode);
        }

        self::assertSame(EventStatus::DRAFT, $fixture->events->record?->status);
        self::assertSame(0, $fixture->events->snapshotCount);
        self::assertSame(1, $fixture->events->readinessChecks);
    }

    public function testExplicitLifecycleSnapshotsVenueAndRestoresArchiveToCompleted(): void
    {
        $fixture = new EventLifecycleFixture();
        $scope = $fixture->create()->scope;

        $fixture->service->activate($fixture->principal, $scope, 'activate-key-0002');
        $fixture->service->complete($fixture->principal, $scope, 'complete-key-0002');
        $fixture->service->archive($fixture->principal, $scope, 'archive-key-0002');
        $fixture->service->restore($fixture->principal, $scope, 'restore-key-0002');

        self::assertSame(EventStatus::COMPLETED, $fixture->events->record?->status);
        self::assertSame(1, $fixture->events->snapshotCount);
        self::assertSame([
            AuditAction::EVENT_CREATED,
            AuditAction::EVENT_ACTIVATED,
            AuditAction::EVENT_COMPLETED,
            AuditAction::EVENT_ARCHIVED,
            AuditAction::EVENT_RESTORED,
        ], $fixture->audit->actions());
    }

    public function testCompleteCannotBeUsedAsAnArchivedRestoreShortcut(): void
    {
        $fixture = new EventLifecycleFixture();
        $scope = $fixture->create()->scope;
        $fixture->service->activate($fixture->principal, $scope, 'activate-key-0003');
        $fixture->service->complete($fixture->principal, $scope, 'complete-key-0003');
        $fixture->service->archive($fixture->principal, $scope, 'archive-key-0003');

        try {
            $fixture->service->complete($fixture->principal, $scope, 'complete-key-0004');
            self::fail('Expected invalid lifecycle transition.');
        } catch (EventLifecycleException $exception) {
            self::assertSame('event_transition_invalid', $exception->safeCode);
        }
        self::assertSame(EventStatus::ARCHIVED, $fixture->events->record?->status);
    }

    public function testCreationRejectsNonWordPressPrincipalBeforePersistence(): void
    {
        $fixture = new EventLifecycleFixture();

        $this->expectException(EventLifecycleException::class);
        try {
            $fixture->service->create(PrincipalContext::anonymous(), $fixture->command(), 'create-key-0005');
        } finally {
            self::assertSame(0, $fixture->events->createdCount);
        }
    }

    public function testCreationFailsClosedWithoutGlobalCreationCapability(): void
    {
        $fixture = new EventLifecycleFixture(false);

        try {
            $fixture->service->create($fixture->principal, $fixture->command(), 'create-key-0006');
            self::fail('Expected creation authorization failure.');
        } catch (EventLifecycleException $exception) {
            self::assertSame('insufficient_event_permission', $exception->safeCode);
        }
        self::assertSame(0, $fixture->events->createdCount);
    }
}

final class EventLifecycleFixture
{
    public readonly EventLifecycleService $service;
    public readonly EventMemoryRepository $events;
    public readonly EventAuditRepository $audit;
    public readonly PrincipalContext $principal;

    public function __construct(bool $canCreate = true)
    {
        $clock = new EventTestClock();
        $transactions = new EventTestTransactions();
        $this->events = new EventMemoryRepository();
        $this->audit = new EventAuditRepository();
        $authorization = new AuthorizationService(
            new EventMembershipReader(),
            new RoleCapabilityPolicy(),
            $clock,
            new EventNoRecovery(),
        );
        $idempotency = new IdempotencyService(
            new EventIdempotencyRepository(),
            $transactions,
            $clock,
            new EventTestRandom(),
            new CanonicalRequestHasher(),
        );
        $audit = new AuditService(
            $this->audit,
            $transactions,
            $clock,
            new AuditPayloadRedactor(),
            new AuditCanonicalizer(),
        );
        $this->service = new EventLifecycleService(
            $this->events,
            new EventCreationTestAuthority($canCreate),
            $authorization,
            $idempotency,
            $audit,
            $clock,
        );
        $this->principal = PrincipalContext::wordpressUser(7);
    }

    public function command(): CreateEvent
    {
        return new CreateEvent(
            'Foundation Event',
            'foundation-event',
            'America/Edmonton',
            new DateTimeImmutable('2026-09-01 18:00:00', new DateTimeZone('UTC')),
            new DateTimeImmutable('2026-09-02 01:00:00', new DateTimeZone('UTC')),
            8,
        );
    }

    public function create(): EventRecord
    {
        return $this->service->create($this->principal, $this->command(), 'create-key-setup')->response;
    }
}

final class EventMemoryRepository implements EventLifecycleRepository
{
    public ?EventRecord $record = null;
    public int $createdCount = 0;
    public bool $defaultConfigurationCreated = false;
    public bool $primaryOwnerCreated = false;
    public int $snapshotCount = 0;
    public int $readinessChecks = 0;
    /** @var list<string> */
    public array $blockers = [];

    public function createDraft(CreateEvent $event, int $primaryOwnerUserId, DateTimeImmutable $now): EventRecord
    {
        $this->createdCount++;
        $this->defaultConfigurationCreated = true;
        $this->primaryOwnerCreated = $primaryOwnerUserId === 7;
        return $this->record = new EventRecord(
            new EventScope(51), $event->name, $event->slug, EventStatus::DRAFT,
            $event->timezone, $event->startsAt, $event->endsAt, $event->venueId,
        );
    }

    public function find(EventScope $scope): ?EventRecord
    {
        return $this->record;
    }

    public function lock(EventScope $scope): ?EventRecord
    {
        return $this->record;
    }

    public function activationReadiness(EventRecord $event): EventActivationReadiness
    {
        $this->readinessChecks++;
        return new EventActivationReadiness($this->blockers);
    }

    public function captureVenueSnapshot(EventRecord $event, ?int $actorUserId, DateTimeImmutable $now): void
    {
        $this->snapshotCount++;
    }

    public function transition(EventRecord $event, EventStatus $target, ?int $actorUserId, DateTimeImmutable $now): EventRecord
    {
        return $this->record = new EventRecord(
            $event->scope, $event->name, $event->slug, $target, $event->timezone,
            $event->startsAt, $event->endsAt, $event->venueId, $event->revision + 1,
        );
    }

    public function updateDraft(EventRecord $current, CreateEvent $replacement, ?int $actorUserId, DateTimeImmutable $now): EventRecord
    {
        return $this->record = new EventRecord(
            $current->scope, $replacement->name, $replacement->slug, EventStatus::DRAFT,
            $replacement->timezone, $replacement->startsAt, $replacement->endsAt,
            $replacement->venueId, $current->revision + 1,
        );
    }
}

final class EventTestTransactions implements TransactionManager
{
    private int $depth = 0;

    public function transactional(callable $operation, ?TransactionOptions $options = null): mixed
    {
        $this->depth++;
        try {
            return $operation();
        } finally {
            $this->depth--;
        }
    }

    public function isActive(): bool
    {
        return $this->depth > 0;
    }

    public function assertNotActive(): void
    {
        if ($this->isActive()) {
            throw new \RuntimeException('transaction_active');
        }
    }
}

final class EventIdempotencyRepository implements IdempotencyRepository
{
    /** @var array<string, IdempotencyRecord> */
    private array $records = [];

    public function claim(
        IdempotencyRequest $request,
        string $leaseToken,
        DateTimeImmutable $now,
        DateTimeImmutable $leaseExpiresAt,
        DateTimeImmutable $recordExpiresAt,
    ): IdempotencyClaimResult {
        $key = $request->eventScopeKey . ':' . $request->principalScope . ':' . $request->operationName . ':' . bin2hex($request->keyDigest);
        if (isset($this->records[$key])) {
            return new IdempotencyClaimResult(IdempotencyClaimState::REPLAY, $this->records[$key]);
        }
        $record = new IdempotencyRecord(count($this->records) + 1, $request->requestFingerprint, 'in_progress', $leaseExpiresAt, null, false);
        $this->records[$key] = $record;
        return new IdempotencyClaimResult(IdempotencyClaimState::ACQUIRED, $record);
    }

    public function complete(int $recordId, string $leaseToken, IdempotencyResultReference $reference, bool $sensitiveResult, DateTimeImmutable $completedAt): void
    {
        foreach ($this->records as $key => $record) {
            if ($record->recordId === $recordId) {
                $this->records[$key] = new IdempotencyRecord($recordId, $record->requestFingerprint, 'completed', null, $reference, $sensitiveResult);
                return;
            }
        }
    }

    public function fail(int $recordId, string $leaseToken, DateTimeImmutable $failedAt): void
    {
        foreach ($this->records as $key => $record) {
            if ($record->recordId === $recordId) {
                unset($this->records[$key]);
            }
        }
    }
}

final class EventAuditRepository implements AuditRepository
{
    /** @var list<AuditRecord> */
    public array $records = [];

    public function lockChainHead(?EventScope $eventScope): ?string
    {
        return $this->records === [] ? null : $this->records[array_key_last($this->records)]->recordHash;
    }

    public function append(AuditRecord $record): int
    {
        $this->records[] = $record;
        return count($this->records);
    }

    /** @return list<AuditAction> */
    public function actions(): array
    {
        return array_map(static fn (AuditRecord $record): AuditAction => $record->action, $this->records);
    }
}

final readonly class EventMembershipReader implements MembershipReader
{
    public function findCurrent(EventScope $eventScope, int $userId): ?MembershipSnapshot
    {
        return new MembershipSnapshot(1, $eventScope, $userId, EventRole::OWNER, true, null);
    }
}

final readonly class EventNoRecovery implements GlobalRecoveryAuthority
{
    public function canRecoverPrimaryOwnership(int $userId): bool
    {
        return false;
    }
}

final readonly class EventCreationTestAuthority implements EventCreationAuthority
{
    public function __construct(private bool $allowed)
    {
    }

    public function canCreateEvent(int $userId): bool
    {
        return $this->allowed && $userId === 7;
    }
}

final readonly class EventTestClock implements Clock
{
    public function now(): DateTimeImmutable
    {
        return new DateTimeImmutable('2026-08-16 18:00:00', new DateTimeZone('UTC'));
    }
}

final readonly class EventTestRandom implements SecureRandom
{
    public function hex(int $bytes): string
    {
        return str_repeat('a', $bytes * 2);
    }
}
