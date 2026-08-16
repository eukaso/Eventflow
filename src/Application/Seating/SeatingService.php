<?php

namespace EventFlow\Application\Seating;

use EventFlow\Application\Audit\AuditAction;
use EventFlow\Application\Audit\AuditEntityType;
use EventFlow\Application\Audit\AuditEvent;
use EventFlow\Application\Audit\AuditService;
use EventFlow\Application\Authorization\AuthorizationService;
use EventFlow\Application\Authorization\Capability;
use EventFlow\Application\Authorization\PrincipalContext;
use EventFlow\Application\Authorization\PrincipalType;
use EventFlow\Application\Clock\Clock;
use EventFlow\Application\Idempotency\IdempotencyOutcome;
use EventFlow\Application\Idempotency\IdempotencyResultReference;
use EventFlow\Application\Idempotency\IdempotencyService;
use EventFlow\Application\Idempotency\IdempotentOperationResult;
use EventFlow\Application\Persistence\EventScope;
use EventFlow\Application\Transaction\TransactionManager;

final readonly class SeatingService
{
    public function __construct(
        private SeatingRepository $seating,
        private AuthorizationService $authorization,
        private IdempotencyService $idempotency,
        private AuditService $audit,
        private Clock $clock,
        private TransactionManager $transactions,
    ) {}

    /** @param list<array{label:string, accessible?:bool}> $seats */
    public function createTable(PrincipalContext $principal, EventScope $scope, string $name, int $capacity, array $seats, string $idempotencyKey): IdempotencyOutcome
    {
        $normalized = []; $labels = [];
        if (trim($name) === '' || strlen(trim($name)) > 190 || $capacity < 1 || $capacity > 65535 || count($seats) > $capacity) throw new SeatingException('seating_table_configuration_invalid');
        foreach ($seats as $seat) { $label = trim((string) ($seat['label'] ?? '')); if ($label === '' || strlen($label) > 64 || isset($labels[strtolower($label)])) throw new SeatingException('seating_seat_label_invalid'); $labels[strtolower($label)] = true; $normalized[] = ['label' => $label, 'accessible' => (bool) ($seat['accessible'] ?? false)]; }
        return $this->idempotency->execute($principal, $scope, 'seating.table.create', $idempotencyKey, ['name' => trim($name), 'capacity' => $capacity, 'seats' => $normalized],
            function () use ($principal, $scope, $name, $capacity, $normalized): IdempotentOperationResult {
                $this->authorization->requireEventCapability($principal, $scope, Capability::MANAGE_SEATING);
                $configured = $this->seating->createTable($scope, trim($name), $capacity, $normalized, $this->actor($principal), $this->clock->now());
                $this->audit->recordRequired(new AuditEvent($principal, $scope, AuditAction::SEATING_TABLE_CREATED, AuditEntityType::SEATING_TABLE, $configured->table->tableId, after: ['name' => $configured->table->name, 'capacity' => $configured->table->capacity, 'seat_count' => count($configured->seats)]));
                return new IdempotentOperationResult(new IdempotencyResultReference('seating_table', $configured->table->tableId, 201), $configured);
            });
    }

    /** @param list<int> $attendeeIds */
    public function createGroup(PrincipalContext $principal, EventScope $scope, string $name, string $category, ConstraintLevel $constraint, int $priority, array $attendeeIds, string $idempotencyKey): IdempotencyOutcome
    {
        $categories = ['family', 'church', 'school', 'work', 'friends', 'association', 'community', 'vip', 'custom'];
        $ids = array_values(array_unique($attendeeIds)); sort($ids, SORT_NUMERIC);
        if (trim($name) === '' || strlen(trim($name)) > 190 || !in_array($category, $categories, true) || $priority < 0 || $priority > 65535 || $ids === []) throw new SeatingException('seating_group_configuration_invalid');
        foreach ($ids as $id) if (!is_int($id) || $id < 1) throw new SeatingException('seating_group_member_invalid');
        return $this->idempotency->execute($principal, $scope, 'seating.group.create', $idempotencyKey, ['name' => trim($name), 'category' => $category, 'constraint' => $constraint->value, 'priority' => $priority, 'attendee_ids' => $ids],
            function () use ($principal, $scope, $name, $category, $constraint, $priority, $ids): IdempotentOperationResult {
                $this->authorization->requireEventCapability($principal, $scope, Capability::MANAGE_SEATING);
                $group = $this->seating->createGroup($scope, trim($name), $category, $constraint, $priority, $ids, $this->actor($principal), $this->clock->now());
                $this->audit->recordRequired(new AuditEvent($principal, $scope, AuditAction::SEATING_GROUP_CREATED, AuditEntityType::SEATING_GROUP, $group->groupId, after: ['name' => $group->name, 'constraint' => $group->constraintLevel->value, 'member_count' => count($group->attendeeIds)]));
                return new IdempotentOperationResult(new IdempotencyResultReference('seating_group', $group->groupId, 201), $group);
            });
    }

    public function readiness(PrincipalContext $principal, EventScope $scope): SeatingReadiness
    {
        $this->authorization->requireEventCapability($principal, $scope, Capability::VIEW_EVENT);
        $snapshot = $this->seating->snapshot($scope);
        $errors = []; $warnings = [];
        if ($snapshot->tables === []) $errors[] = 'seating_tables_required';
        if ($snapshot->attendees === []) $errors[] = 'confirmed_attendees_required';
        $capacity = array_sum(array_map(static fn (SeatingTable $table): int => $table->capacity, $snapshot->tables));
        if ($capacity < count($snapshot->attendees)) $errors[] = 'seating_capacity_insufficient';
        $accessibleSeats = count(array_filter($snapshot->seats, static fn (SeatingSeat $seat): bool => $seat->accessible));
        $accessibleAttendees = count(array_filter($snapshot->attendees, static fn (SeatingAttendee $attendee): bool => $attendee->requiresAccessibleSeat));
        if ($snapshot->seats !== [] && $accessibleSeats < $accessibleAttendees) $errors[] = 'accessible_seating_insufficient';
        foreach ($snapshot->groups as $group) {
            if ($group->constraintLevel === ConstraintLevel::REQUIRED && count($group->attendeeIds) > $this->largestCapacity($snapshot->tables)) $errors[] = 'required_group_exceeds_table_capacity';
        }
        if ($snapshot->seats === []) $warnings[] = 'table_level_seating_mode';
        elseif (count($snapshot->seats) < count($snapshot->attendees)) $errors[] = 'seat_inventory_capacity_insufficient';
        return new SeatingReadiness($errors === [], array_values(array_unique($errors)), $warnings, $snapshot->fingerprint());
    }

    public function recommend(PrincipalContext $principal, EventScope $scope, string $seed): RecommendationPlan
    {
        $this->authorization->requireEventCapability($principal, $scope, Capability::MANAGE_SEATING);
        if (trim($seed) === '' || strlen($seed) > 190) throw new SeatingException('recommendation_seed_invalid');
        // The Event planning-row lock makes generation single-flight per Event.
        return $this->transactions->transactional(function () use ($scope, $seed): RecommendationPlan {
            $snapshot = $this->seating->planningSnapshot($scope);
            $readiness = $this->readinessFromSnapshot($snapshot);
            if (!$readiness->ready) throw new SeatingException($readiness->errors[0]);
            return $this->buildPlan($snapshot, $seed);
        });
    }

    public function assign(PrincipalContext $principal, EventScope $scope, int $attendeeId, int $tableId, ?int $seatId, ?int $expectedAssignmentId, bool $overrideRequiredGroup, ?string $overrideReason, string $idempotencyKey): IdempotencyOutcome
    {
        return $this->idempotency->execute($principal, $scope, 'seating.assign', $idempotencyKey,
            compact('attendeeId', 'tableId', 'seatId', 'expectedAssignmentId', 'overrideRequiredGroup', 'overrideReason'),
            function () use ($principal, $scope, $attendeeId, $tableId, $seatId, $expectedAssignmentId, $overrideRequiredGroup, $overrideReason): IdempotentOperationResult {
                $this->authorization->requireEventCapability($principal, $scope, Capability::MANAGE_SEATING);
                $snapshot = $this->seating->planningSnapshot($scope);
                $this->validateDestination($snapshot, $attendeeId, $tableId, $seatId, $expectedAssignmentId);
                $requiredSplit = $this->splitsRequiredGroup($snapshot, $attendeeId, $tableId);
                if ($requiredSplit) {
                    if (!$overrideRequiredGroup || trim((string) $overrideReason) === '') throw new SeatingException('seating_group_override_required');
                    $this->authorization->requireEventCapability($principal, $scope, Capability::OVERRIDE_REQUIRED_GROUP);
                } elseif ($overrideRequiredGroup) {
                    throw new SeatingException('group_override_not_applicable');
                }
                $assigned = $this->seating->assign($scope, $attendeeId, $tableId, $seatId, $expectedAssignmentId, 'manual', $requiredSplit, $requiredSplit ? trim((string) $overrideReason) : null, $this->actor($principal), $this->clock->now());
                $this->auditAssignment($principal, $scope, $assigned, $requiredSplit ? trim((string) $overrideReason) : null);
                return new IdempotentOperationResult(new IdempotencyResultReference('seating_assignment', $assigned->assignmentId, 200), $assigned);
            });
    }

    public function applyRecommendation(PrincipalContext $principal, EventScope $scope, RecommendationPlan $plan, string $idempotencyKey): IdempotencyOutcome
    {
        $canonical = array_map(static fn (RecommendedPlacement $p): array => [$p->attendeeId, $p->tableId, $p->seatId], $plan->placements);
        return $this->idempotency->execute($principal, $scope, 'seating.recommendation.apply', $idempotencyKey,
            ['fingerprint' => $plan->inputFingerprint, 'algorithm' => $plan->algorithmVersion, 'seed' => $plan->seed, 'placements' => $canonical],
            function () use ($principal, $scope, $plan): IdempotentOperationResult {
                $this->authorization->requireEventCapability($principal, $scope, Capability::MANAGE_SEATING);
                if ($plan->algorithmVersion !== RecommendationPlan::ALGORITHM_VERSION) throw new SeatingException('recommendation_algorithm_unsupported');
                $snapshot = $this->seating->planningSnapshot($scope);
                if (!hash_equals($snapshot->fingerprint(), $plan->inputFingerprint)) throw new SeatingException('seating_recommendation_stale');
                $expectedPlan = $this->buildPlan($snapshot, $plan->seed);
                if ($this->canonicalPlacements($expectedPlan) !== $this->canonicalPlacements($plan)) throw new SeatingException('recommendation_plan_invalid');
                $results = [];
                foreach ($plan->placements as $placement) {
                    $this->validateDestination($snapshot, $placement->attendeeId, $placement->tableId, $placement->seatId, null);
                    if ($this->assignmentFor($snapshot, $placement->attendeeId) !== null) throw new SeatingException('recommendation_manual_assignment_protected');
                    $results[] = $this->seating->assign($scope, $placement->attendeeId, $placement->tableId, $placement->seatId, null, 'automatic', false, null, $this->actor($principal), $this->clock->now());
                }
                foreach ($results as $assignment) $this->auditAssignment($principal, $scope, $assignment, null);
                return new IdempotentOperationResult(new IdempotencyResultReference('event', $scope->eventId, 200), $results);
            });
    }

    public function release(PrincipalContext $principal, EventScope $scope, int $attendeeId, int $expectedAssignmentId, string $idempotencyKey): IdempotencyOutcome
    {
        return $this->idempotency->execute($principal, $scope, 'seating.release', $idempotencyKey, compact('attendeeId', 'expectedAssignmentId'),
            function () use ($principal, $scope, $attendeeId, $expectedAssignmentId): IdempotentOperationResult {
                $this->authorization->requireEventCapability($principal, $scope, Capability::MANAGE_SEATING);
                $snapshot = $this->seating->planningSnapshot($scope);
                $current = $this->assignmentFor($snapshot, $attendeeId);
                if ($current === null || $current->assignmentId !== $expectedAssignmentId) throw new SeatingException('resource_modified');
                $this->seating->release($scope, $attendeeId, $expectedAssignmentId, $this->actor($principal), $this->clock->now());
                $this->audit->recordRequired(new AuditEvent($principal, $scope, AuditAction::SEATING_RELEASED, AuditEntityType::SEATING_ASSIGNMENT, $expectedAssignmentId));
                return new IdempotentOperationResult(new IdempotencyResultReference('attendee', $attendeeId, 200), true);
            });
    }

    private function buildPlan(SeatingSnapshot $snapshot, string $seed): RecommendationPlan
    {
        $occupied = []; $usedSeats = []; $already = [];
        foreach ($snapshot->assignments as $a) { $occupied[$a->tableId] = ($occupied[$a->tableId] ?? 0) + 1; $already[$a->attendeeId] = true; if ($a->seatId !== null) $usedSeats[$a->seatId] = true; }
        $tables = $snapshot->tables;
        usort($tables, static fn (SeatingTable $a, SeatingTable $b): int => [hash('sha256', $seed . ':' . $a->tableId), $a->sortOrder, $a->tableId] <=> [hash('sha256', $seed . ':' . $b->tableId), $b->sortOrder, $b->tableId]);
        $attendeeById = []; foreach ($snapshot->attendees as $a) $attendeeById[$a->attendeeId] = $a;
        $queue = []; $queued = [];
        $groups = $snapshot->groups; usort($groups, static fn (SeatingGroup $a, SeatingGroup $b): int => [$a->constraintLevel === ConstraintLevel::REQUIRED ? 0 : 1, $a->priority, $a->groupId] <=> [$b->constraintLevel === ConstraintLevel::REQUIRED ? 0 : 1, $b->priority, $b->groupId]);
        foreach ($groups as $group) {
            $ids = array_values(array_filter($group->attendeeIds, static fn (int $id): bool => !isset($already[$id]) && !isset($queued[$id])));
            $assignedTables = [];
            foreach ($snapshot->assignments as $assignment) if (in_array($assignment->attendeeId, $group->attendeeIds, true)) $assignedTables[$assignment->tableId] = true;
            if ($group->constraintLevel === ConstraintLevel::REQUIRED && count($assignedTables) > 1) throw new SeatingException('required_group_already_split');
            $pinnedTableId = $group->constraintLevel === ConstraintLevel::REQUIRED && $assignedTables !== [] ? (int) array_key_first($assignedTables) : null;
            if ($ids !== []) { $queue[] = [$ids, 'group:' . $group->name, $pinnedTableId, $group->constraintLevel === ConstraintLevel::REQUIRED]; foreach ($ids as $id) $queued[$id] = true; }
        }
        foreach ($snapshot->attendees as $a) if (!isset($already[$a->attendeeId]) && !isset($queued[$a->attendeeId])) $queue[] = [[$a->attendeeId], 'capacity-fit', null, false];
        $placements = []; $warnings = [];
        foreach ($queue as [$ids, $reason, $pinnedTableId, $required]) {
            $table = $pinnedTableId === null ? $this->tableWithSpace($tables, $occupied, count($ids)) : $this->specificTableWithSpace($tables, $occupied, $pinnedTableId, count($ids));
            if ($table === null && $required) throw new SeatingException('required_group_capacity_insufficient');
            if ($table === null) { $warnings[] = 'group_split_for_capacity'; foreach ($ids as $id) { $single = $this->tableWithSpace($tables, $occupied, 1) ?? throw new SeatingException('seating_capacity_insufficient'); $seat = $this->seatFor($snapshot->seats, $single->tableId, $attendeeById[$id], $usedSeats); $placements[] = new RecommendedPlacement($id, $single->tableId, $seat?->seatId, $reason . ':split'); $occupied[$single->tableId] = ($occupied[$single->tableId] ?? 0) + 1; if ($seat) $usedSeats[$seat->seatId] = true; } continue; }
            foreach ($ids as $id) { if (!isset($attendeeById[$id])) continue; $seat = $this->seatFor($snapshot->seats, $table->tableId, $attendeeById[$id], $usedSeats); $placements[] = new RecommendedPlacement($id, $table->tableId, $seat?->seatId, $reason); $occupied[$table->tableId] = ($occupied[$table->tableId] ?? 0) + 1; if ($seat) $usedSeats[$seat->seatId] = true; }
        }
        return new RecommendationPlan($snapshot->fingerprint(), RecommendationPlan::ALGORITHM_VERSION, $seed, $placements, array_values(array_unique($warnings)));
    }

    private function validateDestination(SeatingSnapshot $s, int $attendeeId, int $tableId, ?int $seatId, ?int $expected): void
    {
        $attendee = null; foreach ($s->attendees as $a) if ($a->attendeeId === $attendeeId) $attendee = $a;
        $table = null; foreach ($s->tables as $t) if ($t->tableId === $tableId) $table = $t;
        if ($attendee === null || $table === null) throw new SeatingException('seating_destination_invalid');
        $current = $this->assignmentFor($s, $attendeeId);
        if (($current?->assignmentId) !== $expected) throw new SeatingException('resource_modified');
        $occupancy = count(array_filter($s->assignments, static fn (SeatingAssignment $a): bool => $a->tableId === $tableId && $a->attendeeId !== $attendeeId));
        if ($occupancy >= $table->capacity) throw new SeatingException('table_capacity_exceeded');
        if ($seatId !== null) {
            $seat = null; foreach ($s->seats as $candidate) if ($candidate->seatId === $seatId && $candidate->tableId === $tableId) $seat = $candidate;
            if ($seat === null) throw new SeatingException('seating_seat_invalid');
            if (count(array_filter($s->assignments, static fn (SeatingAssignment $a): bool => $a->seatId === $seatId && $a->attendeeId !== $attendeeId)) > 0) throw new SeatingException('seat_already_occupied');
            if ($attendee->requiresAccessibleSeat && !$seat->accessible) throw new SeatingException('accessible_seat_required');
        } elseif ($attendee->requiresAccessibleSeat && $s->seats !== []) throw new SeatingException('accessible_seat_required');
    }

    private function splitsRequiredGroup(SeatingSnapshot $s, int $attendeeId, int $tableId): bool
    {
        foreach ($s->groups as $group) if ($group->constraintLevel === ConstraintLevel::REQUIRED && in_array($attendeeId, $group->attendeeIds, true)) {
            foreach ($s->assignments as $assignment) if ($assignment->attendeeId !== $attendeeId && in_array($assignment->attendeeId, $group->attendeeIds, true) && $assignment->tableId !== $tableId) return true;
        }
        return false;
    }

    private function readinessFromSnapshot(SeatingSnapshot $s): SeatingReadiness { $errors = []; if ($s->tables === []) $errors[] = 'seating_tables_required'; if (array_sum(array_map(static fn (SeatingTable $t): int => $t->capacity, $s->tables)) < count($s->attendees)) $errors[] = 'seating_capacity_insufficient'; if ($s->seats !== [] && count($s->seats) < count($s->attendees)) $errors[] = 'seat_inventory_capacity_insufficient'; if ($s->seats !== [] && count(array_filter($s->seats, static fn (SeatingSeat $seat): bool => $seat->accessible)) < count(array_filter($s->attendees, static fn (SeatingAttendee $attendee): bool => $attendee->requiresAccessibleSeat))) $errors[] = 'accessible_seating_insufficient'; return new SeatingReadiness($errors === [], $errors, [], $s->fingerprint()); }
    /** @param list<SeatingTable> $tables @param array<int,int> $occupied */
    private function tableWithSpace(array $tables, array $occupied, int $needed): ?SeatingTable { foreach ($tables as $t) if ($t->capacity - ($occupied[$t->tableId] ?? 0) >= $needed) return $t; return null; }
    /** @param list<SeatingTable> $tables @param array<int,int> $occupied */
    private function specificTableWithSpace(array $tables, array $occupied, int $tableId, int $needed): ?SeatingTable { foreach ($tables as $t) if ($t->tableId === $tableId && $t->capacity - ($occupied[$t->tableId] ?? 0) >= $needed) return $t; return null; }
    /** @param list<SeatingSeat> $seats @param array<int,bool> $used */
    private function seatFor(array $seats, int $tableId, SeatingAttendee $attendee, array $used): ?SeatingSeat { $available = array_values(array_filter($seats, static fn (SeatingSeat $s): bool => $s->tableId === $tableId && !isset($used[$s->seatId]) && (!$attendee->requiresAccessibleSeat || $s->accessible))); usort($available, static fn (SeatingSeat $a, SeatingSeat $b): int => [$a->sortOrder, $a->seatId] <=> [$b->sortOrder, $b->seatId]); if ($attendee->requiresAccessibleSeat && $available === []) throw new SeatingException('accessible_seating_insufficient'); return $available[0] ?? null; }
    /** @param list<SeatingTable> $tables */ private function largestCapacity(array $tables): int { return $tables === [] ? 0 : max(array_map(static fn (SeatingTable $t): int => $t->capacity, $tables)); }
    private function assignmentFor(SeatingSnapshot $s, int $id): ?SeatingAssignment { foreach ($s->assignments as $a) if ($a->attendeeId === $id) return $a; return null; }
    /** @return list<array{int,int,int|null,string}> */ private function canonicalPlacements(RecommendationPlan $plan): array { return array_map(static fn (RecommendedPlacement $p): array => [$p->attendeeId, $p->tableId, $p->seatId, $p->reason], $plan->placements); }
    private function actor(PrincipalContext $p): ?int { return $p->type === PrincipalType::WORDPRESS_USER ? $p->userId : null; }
    private function auditAssignment(PrincipalContext $p, EventScope $s, SeatingAssignment $a, ?string $reason): void { $this->audit->recordRequired(new AuditEvent($p, $s, AuditAction::SEATING_ASSIGNED, AuditEntityType::SEATING_ASSIGNMENT, $a->assignmentId, after: ['attendee_id' => $a->attendeeId, 'table_id' => $a->tableId, 'seat_id' => $a->seatId, 'source' => $a->source, 'group_override' => $a->groupOverride], reason: $reason)); }
}
