<?php

namespace EventFlow\Application\Audit;

use RuntimeException;
use Throwable;

final class AuditException extends RuntimeException
{
    public function __construct(
        public readonly string $safeCode,
        ?Throwable $previous = null,
    ) {
        parent::__construct($safeCode, 0, $previous);
    }
}
