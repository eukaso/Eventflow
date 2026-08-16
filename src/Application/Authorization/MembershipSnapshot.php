<?php

namespace EventFlow\Application\Authorization;

use DateTimeImmutable;
use EventFlow\Application\Persistence\EventScope;
use InvalidArgumentException;

final readonly class MembershipSnapshot
{
    public function __construct(
        public int $membershipId,
        public EventScope $eventScope,
        public int $userId,
        public EventRole $role,
        public bool $isPrimaryOwner,
        public ?DateTimeImmutable $expiresAt,
    ) {
        if ($membershipId < 1 || $userId < 1) {
            throw new InvalidArgumentException('invalid_membership_snapshot');
        }

        if ($isPrimaryOwner && ($role !== EventRole::OWNER || $expiresAt !== null)) {
            throw new InvalidArgumentException('invalid_primary_owner_snapshot');
        }
    }
}
