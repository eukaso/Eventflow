<?php

namespace EventFlow\Application\Job;

use InvalidArgumentException;

final readonly class JobWorkerOptions
{
    public function __construct(
        public int $leaseSeconds = 60,
        public int $baseRetrySeconds = 30,
        public int $maximumRetrySeconds = 3600,
    ) {
        if (
            $leaseSeconds < 10 || $leaseSeconds > 3600
            || $baseRetrySeconds < 1 || $baseRetrySeconds > 3600
            || $maximumRetrySeconds < $baseRetrySeconds || $maximumRetrySeconds > 86400
        ) {
            throw new InvalidArgumentException('invalid_job_worker_options');
        }
    }
}
