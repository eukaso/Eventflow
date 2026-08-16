<?php

namespace EventFlow\Application\Transaction;

use RuntimeException;
use Throwable;

final class TransactionException extends RuntimeException
{
    public function __construct(
        public readonly string $safeCode,
        ?Throwable $previous = null,
    ) {
        parent::__construct($safeCode, 0, $previous);
    }
}
