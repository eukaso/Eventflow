<?php

namespace EventFlow\Tests\Unit\Application\Seating;

use DateTimeImmutable;
use DateTimeZone;
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
use EventFlow\Application\Idempotency\CanonicalRequestHasher;
use EventFlow\Application\Idempotency\IdempotencyClaimResult;
use EventFlow\Application\Idempotency\IdempotencyClaimState;
use EventFlow\Application\Idempotency\IdempotencyRecord;
use EventFlow\Application\Idempotency\IdempotencyRepository;
use EventFlow\Application\Idempotency\IdempotencyRequest;
use EventFlow\Application\Idempotency\IdempotencyResultReference;
use EventFlow\Application\Idempotency\IdempotencyService;
use EventFlow\Application\Persistence\EventScope;
use EventFlow\Application\Seating\ConstraintLevel;
use EventFlow\Application\Seating\ConfiguredTable;
use EventFlow\Application\Seating\RecommendationPlan;
use EventFlow\Application\Seating\RecommendedPlacement;
use EventFlow\Application\Seating\SeatingAssignment;
use EventFlow\Application\Seating\SeatingAttendee;
use EventFlow\Application\Seating\SeatingException;
use EventFlow\Application\Seating\SeatingGroup;
use EventFlow\Application\Seating\SeatingRepository;
use EventFlow\Application\Seating\SeatingSeat;
use EventFlow\Application\Seating\SeatingService;
use EventFlow\Application\Seating\SeatingSnapshot;
use EventFlow\Application\Seating\SeatingTable;
use EventFlow\Application\Security\SecureRandom;
use EventFlow\Application\Transaction\TransactionManager;
use EventFlow\Application\Transaction\TransactionOptions;
use PHPUnit\Framework\TestCase;

final class SeatingServiceTest extends TestCase
{
    public function testFlexibleTableAndRequiredGroupConfigurationAreAuthoritative(): void
    {
        $f = new SeatingFixture(new SeatingSnapshot([new SeatingAttendee(1, 'A'), new SeatingAttendee(2, 'B')], [], [], [], []));
        $configured = $f->service->createTable($f->principal, $f->scope, 'Head Table', 4, [['label' => 'A', 'accessible' => true], ['label' => 'B']], 'table-create')->response;
        $group = $f->service->createGroup($f->principal, $f->scope, 'Family', 'family', ConstraintLevel::REQUIRED, 1, [2, 1], 'group-create')->response;
        self::assertInstanceOf(ConfiguredTable::class, $configured);
        self::assertCount(2, $configured->seats);
        self::assertSame([1, 2], $group->attendeeIds);
        self::assertSame(ConstraintLevel::REQUIRED, $group->constraintLevel);
    }

    public function testReadinessReportsCapacityAndAccessibilityFailures(): void
    {
        $f = new SeatingFixture(new SeatingSnapshot(
            [new SeatingAttendee(1, 'A', true), new SeatingAttendee(2, 'B')],
            [new SeatingTable(1, 'One', 1)], [new SeatingSeat(1, 1, '1', false)], [], [],
        ));
        $report = $f->service->readiness($f->principal, $f->scope);
        self::assertFalse($report->ready);
        self::assertContains('seating_capacity_insufficient', $report->errors);
        self::assertContains('accessible_seating_insufficient', $report->errors);
    }

    public function testRecommendationsAreReproducibleKeepGroupsTogetherAndProtectManualAssignments(): void
    {
        $f = new SeatingFixture(SeatingFixture::standard());
        $first = $f->service->recommend($f->principal, $f->scope, 'published-seed');
        $second = $f->service->recommend($f->principal, $f->scope, 'published-seed');
        self::assertEquals($first, $second);
        self::assertSame(RecommendationPlan::ALGORITHM_VERSION, $first->algorithmVersion);
        self::assertSame([2, 3], array_map(static fn (RecommendedPlacement $p): int => $p->attendeeId, $first->placements));
        self::assertSame($first->placements[0]->tableId, $first->placements[1]->tableId);
        self::assertNotSame(1, $first->placements[0]->attendeeId);
    }

    public function testManualMoveEnforcesStaleStateAccessibilityAndRequiredGroupReason(): void
    {
        $f = new SeatingFixture(SeatingFixture::standard());
        foreach ([
            [2, 2, 4, null, false, null, 'accessible_seat_required'],
            [2, 2, 3, 99, false, null, 'resource_modified'],
            [2, 2, 3, null, false, null, 'seating_group_override_required'],
        ] as [$attendee, $table, $seat, $expected, $override, $reason, $code]) {
            try { $f->service->assign($f->principal, $f->scope, $attendee, $table, $seat, $expected, $override, $reason, 'case-' . $code); self::fail('Expected failure.'); }
            catch (SeatingException $e) { self::assertSame($code, $e->safeCode); }
        }
        $result = $f->service->assign($f->principal, $f->scope, 2, 2, 3, null, true, 'Host approved split', 'override-ok')->response;
        self::assertInstanceOf(SeatingAssignment::class, $result);
        self::assertTrue($result->groupOverride);
    }

    public function testRecommendationApplyRejectsChangedSnapshot(): void
    {
        $f = new SeatingFixture(SeatingFixture::standard());
        $plan = $f->service->recommend($f->principal, $f->scope, 'seed');
        $f->repository->snapshot = new SeatingSnapshot($f->repository->snapshot->attendees, $f->repository->snapshot->tables, $f->repository->snapshot->seats, $f->repository->snapshot->groups, [...$f->repository->snapshot->assignments, new SeatingAssignment(9, 3, 2, 3, 'manual')]);
        try { $f->service->applyRecommendation($f->principal, $f->scope, $plan, 'stale-plan'); self::fail('Expected stale plan.'); }
        catch (SeatingException $e) { self::assertSame('seating_recommendation_stale', $e->safeCode); }
    }
}

final class SeatingFixture
{
    public readonly EventScope $scope; public readonly PrincipalContext $principal; public readonly SeatingMemoryRepository $repository; public readonly SeatingService $service;
    public function __construct(SeatingSnapshot $snapshot)
    {
        $this->scope = new EventScope(50); $this->principal = PrincipalContext::wordpressUser(7); $this->repository = new SeatingMemoryRepository($snapshot);
        $clock = new SeatingClock(); $tx = new SeatingTransactions();
        $auth = new AuthorizationService(new SeatingMembershipReader(), new RoleCapabilityPolicy(), $clock, new SeatingNoRecovery());
        $idem = new IdempotencyService(new SeatingIdempotencyRepository(), $tx, $clock, new SeatingRandom(), new CanonicalRequestHasher());
        $audit = new AuditService(new SeatingAuditRepository(), $tx, $clock, new AuditPayloadRedactor(), new AuditCanonicalizer());
        $this->service = new SeatingService($this->repository, $auth, $idem, $audit, $clock, $tx);
    }
    public static function standard(): SeatingSnapshot
    {
        return new SeatingSnapshot(
            [new SeatingAttendee(1, 'Manual'), new SeatingAttendee(2, 'Accessible', true), new SeatingAttendee(3, 'Friend')],
            [new SeatingTable(1, 'One', 3, 1), new SeatingTable(2, 'Two', 3, 2)],
            [new SeatingSeat(1, 1, 'A', true, 1), new SeatingSeat(2, 1, 'B', true, 2), new SeatingSeat(5, 1, 'C', false, 3), new SeatingSeat(3, 2, 'A', true, 1), new SeatingSeat(4, 2, 'B', false, 2), new SeatingSeat(6, 2, 'C', false, 3)],
            [new SeatingGroup(1, 'Required friends', ConstraintLevel::REQUIRED, 1, [1, 2, 3])],
            [new SeatingAssignment(1, 1, 1, 1, 'manual')],
        );
    }
}

final class SeatingMemoryRepository implements SeatingRepository
{
    public int $next = 10; public function __construct(public SeatingSnapshot $snapshot) {}
    public function createTable(EventScope $scope, string $name, int $capacity, array $seats, ?int $actorUserId, DateTimeImmutable $now): ConfiguredTable { $table = new SeatingTable($this->next++, $name, $capacity); $created = []; foreach ($seats as $i => $seat) $created[] = new SeatingSeat($this->next++, $table->tableId, $seat['label'], $seat['accessible'], $i + 1); $this->snapshot = new SeatingSnapshot($this->snapshot->attendees, [...$this->snapshot->tables, $table], [...$this->snapshot->seats, ...$created], $this->snapshot->groups, $this->snapshot->assignments); return new ConfiguredTable($table, $created); }
    public function createGroup(EventScope $scope, string $name, string $category, ConstraintLevel $constraint, int $priority, array $attendeeIds, ?int $actorUserId, DateTimeImmutable $now): SeatingGroup { $group = new SeatingGroup($this->next++, $name, $constraint, $priority, $attendeeIds); $this->snapshot = new SeatingSnapshot($this->snapshot->attendees, $this->snapshot->tables, $this->snapshot->seats, [...$this->snapshot->groups, $group], $this->snapshot->assignments); return $group; }
    public function planningSnapshot(EventScope $scope): SeatingSnapshot { return $this->snapshot; }
    public function snapshot(EventScope $scope): SeatingSnapshot { return $this->snapshot; }
    public function assign(EventScope $scope, int $attendeeId, int $tableId, ?int $seatId, ?int $expectedAssignmentId, string $source, bool $groupOverride, ?string $overrideReason, ?int $actorUserId, DateTimeImmutable $now): SeatingAssignment { $assignments = array_values(array_filter($this->snapshot->assignments, static fn (SeatingAssignment $a): bool => $a->attendeeId !== $attendeeId)); $assignment = new SeatingAssignment($this->next++, $attendeeId, $tableId, $seatId, $source, $groupOverride, $overrideReason); $this->snapshot = new SeatingSnapshot($this->snapshot->attendees, $this->snapshot->tables, $this->snapshot->seats, $this->snapshot->groups, [...$assignments, $assignment]); return $assignment; }
    public function release(EventScope $scope, int $attendeeId, int $expectedAssignmentId, ?int $actorUserId, DateTimeImmutable $now): void { $this->snapshot = new SeatingSnapshot($this->snapshot->attendees, $this->snapshot->tables, $this->snapshot->seats, $this->snapshot->groups, array_values(array_filter($this->snapshot->assignments, static fn (SeatingAssignment $a): bool => $a->assignmentId !== $expectedAssignmentId))); }
}

final class SeatingMembershipReader implements MembershipReader { public function findCurrent(EventScope $eventScope, int $userId): ?MembershipSnapshot { return new MembershipSnapshot(1, $eventScope, $userId, EventRole::OWNER, false, null); } }
final readonly class SeatingNoRecovery implements GlobalRecoveryAuthority { public function canRecoverPrimaryOwnership(int $userId): bool { return false; } }
final readonly class SeatingClock implements Clock { public function now(): DateTimeImmutable { return new DateTimeImmutable('2026-08-16 20:00:00', new DateTimeZone('UTC')); } }
final readonly class SeatingRandom implements SecureRandom { public function hex(int $bytes): string { return str_repeat('d', $bytes * 2); } }
final class SeatingTransactions implements TransactionManager { private int $depth = 0; public function transactional(callable $operation, ?TransactionOptions $options = null): mixed { $this->depth++; try { return $operation(); } finally { $this->depth--; } } public function isActive(): bool { return $this->depth > 0; } public function assertNotActive(): void { if ($this->depth) throw new \RuntimeException('active'); } }
final class SeatingAuditRepository implements AuditRepository { private array $records = []; public function lockChainHead(?EventScope $eventScope): ?string { return $this->records === [] ? null : $this->records[array_key_last($this->records)]->recordHash; } public function append(AuditRecord $record): int { $this->records[] = $record; return count($this->records); } }
final class SeatingIdempotencyRepository implements IdempotencyRepository
{
    private array $records = [];
    public function claim(IdempotencyRequest $request, string $leaseToken, DateTimeImmutable $now, DateTimeImmutable $leaseExpiresAt, DateTimeImmutable $recordExpiresAt): IdempotencyClaimResult { $key = $request->operationName . bin2hex($request->keyDigest); if (isset($this->records[$key])) return new IdempotencyClaimResult(IdempotencyClaimState::REPLAY, $this->records[$key]); return new IdempotencyClaimResult(IdempotencyClaimState::ACQUIRED, $this->records[$key] = new IdempotencyRecord(count($this->records) + 1, $request->requestFingerprint, 'in_progress', $leaseExpiresAt, null, false)); }
    public function complete(int $recordId, string $leaseToken, IdempotencyResultReference $result, bool $sensitive, DateTimeImmutable $completedAt): void { foreach ($this->records as $key => $record) if ($record->recordId === $recordId) $this->records[$key] = new IdempotencyRecord($recordId, $record->requestFingerprint, 'completed', null, $result, $sensitive); }
    public function fail(int $recordId, string $leaseToken, DateTimeImmutable $failedAt): void { foreach ($this->records as $key => $record) if ($record->recordId === $recordId) unset($this->records[$key]); }
}
