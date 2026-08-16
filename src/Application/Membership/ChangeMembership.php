<?php

namespace EventFlow\Application\Membership;

use DateTimeImmutable;
use EventFlow\Application\Authorization\EventRole;
use EventFlow\Application\Persistence\EventScope;
use InvalidArgumentException;

final readonly class ChangeMembership
{
    public function __construct(
        public EventScope $eventScope,
        public int $membershipId,
        public EventRole $role,
        public ?DateTimeImmutable $expiresAt = null,
    ) {
        if ($membershipId < 1) {
            throw new InvalidArgumentException('invalid_membership_id');
        }
    }

    /** @return array<string, int|string|null> */
    public function canonicalRequest(): array
    {
        return [
            'event_id' => $this->eventScope->eventId,
            'membership_id' => $this->membershipId,
            'role' => $this->role->value,
            'expires_at' => $this->expiresAt?->format(DATE_ATOM),
        ];
    }
}
