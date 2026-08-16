<?php

namespace EventFlow\Application\Job;

use RuntimeException;
use Throwable;

class JobException extends RuntimeException
{
    public function __construct(
        public readonly string $safeCode,
        ?Throwable $previous = null,
    ) {
        parent::__construct($safeCode, 0, $previous);
    }
}
