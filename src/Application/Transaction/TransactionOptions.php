<?php

namespace EventFlow\Application\Transaction;

use InvalidArgumentException;

final readonly class TransactionOptions
{
    public function __construct(
        public NestedTransactionMode $nestedMode = NestedTransactionMode::JOIN,
        public int $maxAttempts = 1,
    ) {
        if ($maxAttempts < 1 || $maxAttempts > 3) {
            throw new InvalidArgumentException('invalid_transaction_attempt_limit');
        }
    }
}
