<?php

namespace EventFlow\Application\Membership;

use EventFlow\Application\Persistence\EventScope;
use InvalidArgumentException;

final readonly class TransferPrimaryOwner
{
    public function __construct(
        public EventScope $eventScope,
        public int $expectedCurrentMembershipId,
        public int $targetMembershipId,
    ) {
        if ($expectedCurrentMembershipId < 1 || $targetMembershipId < 1) {
            throw new InvalidArgumentException('invalid_primary_owner_transfer');
        }
    }

    /** @return array<string, int> */
    public function canonicalRequest(): array
    {
        return [
            'event_id' => $this->eventScope->eventId,
            'expected_current_membership_id' => $this->expectedCurrentMembershipId,
            'target_membership_id' => $this->targetMembershipId,
        ];
    }
}
