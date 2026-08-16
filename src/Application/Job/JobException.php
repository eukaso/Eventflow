<?php

namespace EventFlow\Application\Job;

use EventFlow\Application\Error\ControlledFailure;
use RuntimeException;
use Throwable;

class JobException extends RuntimeException implements ControlledFailure
{
    public function __construct(
        public readonly string $safeCode,
        ?Throwable $previous = null,
    ) {
        parent::__construct($safeCode, 0, $previous);
    }

    public function safeCode(): string
    {
        return $this->safeCode;
    }
}
