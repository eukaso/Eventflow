<?php

namespace EventFlow\Infrastructure\Persistence;

use RuntimeException;
use Throwable;

final class PersistenceException extends RuntimeException
{
    public function __construct(
        public readonly string $safeCode,
        ?Throwable $previous = null,
    ) {
        parent::__construct($safeCode, 0, $previous);
    }
}
