<?php

namespace EventFlow\Application\Invitation;

use DateTimeImmutable;
use EventFlow\Application\Persistence\EventScope;
use InvalidArgumentException;

final readonly class CreateInvitation
{
    public function __construct(
        public EventScope $eventScope,
        public string $primaryName,
        public int $capacity = 1,
        public ?string $primaryEmail = null,
        public ?string $primaryPhone = null,
        public ?DateTimeImmutable $tokenExpiresAt = null,
    ) {
        if (trim($primaryName) === '' || strlen($primaryName) > 190 || $capacity < 1 || $capacity > 65535) {
            throw new InvalidArgumentException('invalid_invitation');
        }
        if ($primaryEmail !== null && (strlen($primaryEmail) > 190 || filter_var($primaryEmail, FILTER_VALIDATE_EMAIL) === false)) {
            throw new InvalidArgumentException('invalid_invitation_email');
        }
        if ($primaryPhone !== null && (trim($primaryPhone) === '' || strlen($primaryPhone) > 40)) {
            throw new InvalidArgumentException('invalid_invitation_phone');
        }
    }

    /** @return array<string, int|string|null> */
    public function canonicalRequest(): array
    {
        return [
            'event_id' => $this->eventScope->eventId,
            'primary_name' => trim($this->primaryName),
            'capacity' => $this->capacity,
            'primary_email' => $this->primaryEmail === null ? null : strtolower(trim($this->primaryEmail)),
            'primary_phone' => $this->primaryPhone === null ? null : trim($this->primaryPhone),
            'token_expires_at' => $this->tokenExpiresAt?->format(DATE_ATOM),
        ];
    }
}
