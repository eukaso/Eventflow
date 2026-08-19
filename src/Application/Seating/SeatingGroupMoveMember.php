<?php

namespace EventFlow\Application\Seating;

final readonly class SeatingGroupMoveMember
{
    public function __construct(
        public int $attendeeId,
        public ?int $seatId,
        public ?int $expectedAssignmentId,
    ) {
        if ($attendeeId < 1 || ($seatId !== null && $seatId < 1) || ($expectedAssignmentId !== null && $expectedAssignmentId < 1)) {
            throw new SeatingException('seating_group_move_invalid');
        }
    }
}
