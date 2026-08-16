<?php

namespace EventFlow\Application\Seating;

final readonly class SeatingAssignment
{
    public function __construct(
        public int $assignmentId,
        public int $attendeeId,
        public int $tableId,
        public ?int $seatId,
        public string $source,
        public bool $groupOverride = false,
        public ?string $overrideReason = null,
    ) {
        if ($assignmentId < 1 || $attendeeId < 1 || $tableId < 1 || ($seatId !== null && $seatId < 1)) throw new SeatingException('seating_assignment_invalid');
    }
}
