<?php

namespace EventFlow\Application\Membership;

final readonly class MembershipPage
{
    /** @param list<MembershipRecord> $memberships */
    public function __construct(
        public array $memberships,
        public ?int $nextAfterMembershipId,
    ) {
    }
}
