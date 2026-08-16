<?php

namespace EventFlow\Application\Seating;

use DateTimeImmutable;
use EventFlow\Application\Persistence\EventScope;

interface SeatingRepository
{
    /** @param list<array{label:string, accessible:bool}> $seats */
    public function createTable(EventScope $scope, string $name, int $capacity, array $seats, ?int $actorUserId, DateTimeImmutable $now): ConfiguredTable;
    /** @param list<int> $attendeeIds */
    public function createGroup(EventScope $scope, string $name, string $category, ConstraintLevel $constraint, int $priority, array $attendeeIds, ?int $actorUserId, DateTimeImmutable $now): SeatingGroup;
    /** Locks the Event planning row, then tables, seats, attendees and active assignments in deterministic ID order. */
    public function planningSnapshot(EventScope $scope): SeatingSnapshot;
    public function snapshot(EventScope $scope): SeatingSnapshot;
    public function assign(EventScope $scope, int $attendeeId, int $tableId, ?int $seatId, ?int $expectedAssignmentId, string $source, bool $groupOverride, ?string $overrideReason, ?int $actorUserId, DateTimeImmutable $now): SeatingAssignment;
    public function release(EventScope $scope, int $attendeeId, int $expectedAssignmentId, ?int $actorUserId, DateTimeImmutable $now): void;
}
