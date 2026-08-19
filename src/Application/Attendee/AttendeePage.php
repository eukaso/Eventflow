<?php

namespace EventFlow\Application\Attendee;

final readonly class AttendeePage
{
    /** @param list<AttendeeRecord> $attendees */
    public function __construct(
        public array $attendees,
        public ?int $nextAfterAttendeeId,
    ) {
    }
}
