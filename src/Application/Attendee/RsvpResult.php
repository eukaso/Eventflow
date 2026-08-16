<?php

namespace EventFlow\Application\Attendee;

final readonly class RsvpResult
{
    /** @param list<AttendeeRecord> $attendees */
    public function __construct(public RsvpInvitation $invitation, public array $attendees) {}
}
