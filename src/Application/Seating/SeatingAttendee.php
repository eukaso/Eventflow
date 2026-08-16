<?php

namespace EventFlow\Application\Seating;

final readonly class SeatingAttendee
{
    public function __construct(
        public int $attendeeId,
        public string $displayName,
        public bool $requiresAccessibleSeat = false,
    ) {
        if ($attendeeId < 1 || trim($displayName) === '') throw new SeatingException('seating_attendee_invalid');
    }
}
