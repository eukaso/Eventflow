<?php

namespace EventFlow\Application\Job;

final readonly class JobReconciliationResult
{
    public function __construct(
        public int $recovered,
        public int $deadLettered,
        public bool $runnableWorkExists,
    ) {
    }
}
