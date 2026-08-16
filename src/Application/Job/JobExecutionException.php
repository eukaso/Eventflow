<?php

namespace EventFlow\Application\Job;

use Throwable;

final class JobExecutionException extends JobException
{
    public function __construct(
        string $safeCode,
        public readonly bool $retryable = true,
        ?Throwable $previous = null,
    ) {
        parent::__construct($safeCode, $previous);
    }
}
