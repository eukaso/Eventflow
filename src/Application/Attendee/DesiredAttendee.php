<?php

namespace EventFlow\Application\Attendee;

use InvalidArgumentException;

final readonly class DesiredAttendee
{
    public function __construct(
        public string $displayName,
        public AttendeeRole $role,
        public ?int $attendeeId = null,
        public ?string $email = null,
        public ?string $phone = null,
        public ?string $dietaryRequirements = null,
        public ?string $accessibilityRequirements = null,
    ) {
        if (trim($displayName) === '' || strlen($displayName) > 190 || ($attendeeId !== null && $attendeeId < 1)) {
            throw new InvalidArgumentException('invalid_desired_attendee');
        }
        if ($email !== null && (strlen($email) > 190 || filter_var($email, FILTER_VALIDATE_EMAIL) === false)) {
            throw new InvalidArgumentException('invalid_attendee_email');
        }
        if ($phone !== null && (trim($phone) === '' || strlen($phone) > 40)) {
            throw new InvalidArgumentException('invalid_attendee_phone');
        }
    }

    /** @return array<string, int|string|null> */
    public function canonical(): array
    {
        return [
            'attendee_id' => $this->attendeeId,
            'display_name' => trim($this->displayName),
            'role' => $this->role->value,
            'email' => $this->email === null ? null : strtolower(trim($this->email)),
            'phone' => $this->phone === null ? null : trim($this->phone),
            'dietary_requirements' => $this->dietaryRequirements,
            'accessibility_requirements' => $this->accessibilityRequirements,
        ];
    }
}
