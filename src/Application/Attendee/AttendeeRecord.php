<?php

namespace EventFlow\Application\Attendee;

use EventFlow\Application\Persistence\EventScope;
use InvalidArgumentException;

final readonly class AttendeeRecord
{
    public function __construct(
        public int $attendeeId,
        public EventScope $eventScope,
        public int $invitationId,
        public string $displayName,
        public AttendeeRole $role,
        public AttendanceStatus $status,
        public ?string $email = null,
        public ?string $phone = null,
        public ?string $dietaryRequirements = null,
        public ?string $accessibilityRequirements = null,
    ) {
        if ($attendeeId < 1 || $invitationId < 1 || trim($displayName) === '') {
            throw new InvalidArgumentException('invalid_attendee_record');
        }
    }

    public function active(): bool
    {
        return $this->status === AttendanceStatus::PENDING || $this->status === AttendanceStatus::CONFIRMED;
    }
}
