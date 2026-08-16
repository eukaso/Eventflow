<?php

namespace EventFlow\Application\Membership;

use DateTimeImmutable;
use EventFlow\Application\Authorization\EventRole;
use EventFlow\Application\Persistence\EventScope;
use InvalidArgumentException;

final readonly class GrantMembership
{
    public function __construct(
        public EventScope $eventScope,
        public int $userId,
        public EventRole $role,
        public ?DateTimeImmutable $expiresAt = null,
    ) {
        if ($userId < 1) {
            throw new InvalidArgumentException('invalid_membership_user');
        }
    }

    /** @return array<string, int|string|null> */
    public function canonicalRequest(): array
    {
        return [
            'event_id' => $this->eventScope->eventId,
            'user_id' => $this->userId,
            'role' => $this->role->value,
            'expires_at' => $this->expiresAt?->format(DATE_ATOM),
        ];
    }
}
