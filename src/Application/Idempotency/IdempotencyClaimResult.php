<?php

namespace EventFlow\Application\Idempotency;

final readonly class IdempotencyClaimResult
{
    public function __construct(
        public IdempotencyClaimState $state,
        public IdempotencyRecord $record,
    ) {
    }
}
