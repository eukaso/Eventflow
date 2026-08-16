<?php

namespace EventFlow\Application\Idempotency;

use RuntimeException;
use Throwable;

final class IdempotencyException extends RuntimeException
{
    public function __construct(
        public readonly string $safeCode,
        ?Throwable $previous = null,
    ) {
        parent::__construct($safeCode, 0, $previous);
    }
}
