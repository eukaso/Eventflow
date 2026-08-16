<?php

namespace EventFlow\Application\Idempotency;

use InvalidArgumentException;

final readonly class IdempotencyOptions
{
    public function __construct(
        public int $leaseSeconds = 30,
        public int $retentionSeconds = 86400,
    ) {
        if ($leaseSeconds < 5 || $leaseSeconds > 300) {
            throw new InvalidArgumentException('invalid_idempotency_lease_duration');
        }

        if ($retentionSeconds <= $leaseSeconds || $retentionSeconds > 2_592_000) {
            throw new InvalidArgumentException('invalid_idempotency_retention_duration');
        }
    }
}
