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

final readonly class SeatingGroupMoveService implements SeatingGroupMoves
{
    public function __construct(
        private SeatingRepository $seating,
        private AuthorizationService $authorization,
        private IdempotencyService $idempotency,
        private AuditService $audit,
        private Clock $clock,
    ) {}

    public function moveGroup(PrincipalContext $principal, EventScope $scope, int $groupId, int $tableId, int $expectedGroupRevision, array $members, bool $overrideRequiredGroups, ?string $overrideReason, string $idempotencyKey): IdempotencyOutcome
    {
        if ($groupId < 1 || $tableId < 1 || $expectedGroupRevision < 1 || $members === []) throw new SeatingException('seating_group_move_invalid');
        $normalized = [];
        foreach ($members as $member) {
            if (!$member instanceof SeatingGroupMoveMember || isset($normalized[$member->attendeeId])) throw new SeatingException('seating_group_move_invalid');
            $normalized[$member->attendeeId] = $member;
        }
        ksort($normalized, SORT_NUMERIC);
        $reason = $overrideReason === null ? null : trim($overrideReason);
        if (!$overrideRequiredGroups && $reason !== null) throw new SeatingException('seating_group_move_invalid');
        $canonicalMembers = array_map(static fn (SeatingGroupMoveMember $member): array => [
            'attendee_id' => $member->attendeeId,
            'seat_id' => $member->seatId,
            'expected_assignment_id' => $member->expectedAssignmentId,
        ], array_values($normalized));

        return $this->idempotency->execute($principal, $scope, 'seating.group.move', $idempotencyKey, [
            'group_id' => $groupId,
            'table_id' => $tableId,
            'expected_group_revision' => $expectedGroupRevision,
            'members' => $canonicalMembers,
            'override_required_groups' => $overrideRequiredGroups,
            'override_reason' => $reason,
        ], function () use ($principal, $scope, $groupId, $tableId, $expectedGroupRevision, $normalized, $overrideRequiredGroups, $reason): IdempotentOperationResult {
            $this->authorization->requireEventCapability($principal, $scope, Capability::MANAGE_SEATING);
            $snapshot = $this->seating->planningSnapshot($scope);
            $group = $this->group($snapshot, $groupId);
            if ($group->revision !== $expectedGroupRevision) throw new SeatingException('resource_modified');

            $memberIds = $group->attendeeIds;
            sort($memberIds, SORT_NUMERIC);
            if ($memberIds !== array_keys($normalized)) throw new SeatingException('seating_group_members_modified');
            $table = $this->table($snapshot, $tableId);
            $attendees = []; foreach ($snapshot->attendees as $attendee) $attendees[$attendee->attendeeId] = $attendee;
            $assignments = []; foreach ($snapshot->assignments as $assignment) $assignments[$assignment->attendeeId] = $assignment;
            $moving = array_fill_keys($memberIds, true);

            $outsideOccupancy = count(array_filter($snapshot->assignments, static fn (SeatingAssignment $assignment): bool => $assignment->tableId === $tableId && !isset($moving[$assignment->attendeeId])));
            if ($outsideOccupancy + count($memberIds) > $table->capacity) throw new SeatingException('table_capacity_exceeded');

            $occupiedSeats = [];
            foreach ($snapshot->assignments as $assignment) if ($assignment->seatId !== null) $occupiedSeats[$assignment->seatId] = $assignment->attendeeId;
            $seatById = []; foreach ($snapshot->seats as $seat) $seatById[$seat->seatId] = $seat;
            $requestedSeats = [];
            foreach ($normalized as $attendeeId => $member) {
                $attendee = $attendees[$attendeeId] ?? throw new SeatingException('seating_group_member_invalid');
                $current = $assignments[$attendeeId] ?? null;
                if (($current?->assignmentId) !== $member->expectedAssignmentId) throw new SeatingException('resource_modified');
                if ($member->seatId !== null) {
                    $seat = $seatById[$member->seatId] ?? null;
                    if ($seat === null || $seat->tableId !== $tableId) throw new SeatingException('seating_seat_invalid');
                    if (isset($requestedSeats[$member->seatId])) throw new SeatingException('seat_already_occupied');
                    $occupant = $occupiedSeats[$member->seatId] ?? null;
                    if ($occupant !== null && $occupant !== $attendeeId) throw new SeatingException('seat_already_occupied');
                    if ($attendee->requiresAccessibleSeat && !$seat->accessible) throw new SeatingException('accessible_seat_required');
                    $requestedSeats[$member->seatId] = true;
                } elseif ($attendee->requiresAccessibleSeat && $snapshot->seats !== []) {
                    throw new SeatingException('accessible_seat_required');
                }
            }

            $requiredSplit = $this->splitsRequiredGroup($snapshot, $moving, $tableId);
            if ($requiredSplit) {
                if (!$overrideRequiredGroups || $reason === null || $reason === '') throw new SeatingException('seating_group_override_required');
                $this->authorization->requireEventCapability($principal, $scope, Capability::OVERRIDE_REQUIRED_GROUP);
            } elseif ($overrideRequiredGroups) {
                throw new SeatingException('group_override_not_applicable');
            }

            $results = [];
            foreach ($normalized as $attendeeId => $member) {
                $current = $assignments[$attendeeId] ?? null;
                if ($current !== null && $current->tableId === $tableId && $current->seatId === $member->seatId) {
                    $results[] = $current;
                    continue;
                }
                $results[] = $this->seating->assign($scope, $attendeeId, $tableId, $member->seatId, $member->expectedAssignmentId, 'manual', $requiredSplit, $requiredSplit ? $reason : null, $this->actor($principal), $this->clock->now());
            }
            $move = new SeatingGroupMove($groupId, $tableId, $results, $requiredSplit, $requiredSplit ? $reason : null);
            $this->audit->recordRequired(new AuditEvent($principal, $scope, AuditAction::SEATING_GROUP_MOVED, AuditEntityType::SEATING_GROUP, $groupId, after: [
                'table_id' => $tableId,
                'assignment_ids' => array_map(static fn (SeatingAssignment $assignment): int => $assignment->assignmentId, $results),
                'required_group_override' => $requiredSplit,
            ], reason: $requiredSplit ? $reason : null));
            return new IdempotentOperationResult(new IdempotencyResultReference('seating_group', $groupId, 200), $move);
        });
    }

    private function group(SeatingSnapshot $snapshot, int $groupId): SeatingGroup
    {
        foreach ($snapshot->groups as $group) if ($group->groupId === $groupId) return $group;
        throw new SeatingException('resource_not_found');
    }

    private function table(SeatingSnapshot $snapshot, int $tableId): SeatingTable
    {
        foreach ($snapshot->tables as $table) if ($table->tableId === $tableId) return $table;
        throw new SeatingException('resource_not_found');
    }

    /** @param array<int, bool> $moving */
    private function splitsRequiredGroup(SeatingSnapshot $snapshot, array $moving, int $tableId): bool
    {
        foreach ($snapshot->groups as $group) {
            if ($group->constraintLevel !== ConstraintLevel::REQUIRED || array_intersect($group->attendeeIds, array_keys($moving)) === []) continue;
            $tables = [];
            foreach ($group->attendeeIds as $attendeeId) {
                if (isset($moving[$attendeeId])) { $tables[$tableId] = true; continue; }
                foreach ($snapshot->assignments as $assignment) if ($assignment->attendeeId === $attendeeId) { $tables[$assignment->tableId] = true; break; }
            }
            if (count($tables) > 1) return true;
        }
        return false;
    }

    private function actor(PrincipalContext $principal): ?int
    {
        return $principal->type === PrincipalType::WORDPRESS_USER ? $principal->userId : null;
    }
}
