<?php

namespace EventFlow\Application\Invitation;

final readonly class CompanionRolloutResult
{
    public function __construct(
        public int $updatedInvitations,
        public int $totalCapacity = CompanionRolloutPolicy::DEFAULT_TOTAL_CAPACITY,
    ) {
    }
}
