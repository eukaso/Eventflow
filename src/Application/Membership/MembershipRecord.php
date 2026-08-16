<?php

namespace EventFlow\Application\Membership;

use DateTimeImmutable;
use EventFlow\Application\Authorization\EventRole;
use EventFlow\Application\Persistence\EventScope;
use InvalidArgumentException;

final readonly class MembershipRecord
{
    public function __construct(
        public int $membershipId,
        public EventScope $eventScope,
        public int $userId,
        public EventRole $role,
        public MembershipStatus $status,
        public bool $isPrimaryOwner,
        public ?DateTimeImmutable $expiresAt,
    ) {
        if ($membershipId < 1 || $userId < 1) {
            throw new InvalidArgumentException('invalid_membership_record');
        }
        if ($isPrimaryOwner && ($role !== EventRole::OWNER || $status !== MembershipStatus::ACTIVE || $expiresAt !== null)) {
            throw new InvalidArgumentException('invalid_primary_owner_record');
        }
    }
}
