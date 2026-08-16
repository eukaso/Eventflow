<?php

namespace EventFlow\Application\Job;

use Closure;
use EventFlow\Application\Authorization\PrincipalContext;
use EventFlow\Application\Persistence\EventScope;

final readonly class JobExecutionContext
{
    /**
     * @param array<string, mixed> $payload
     * @param Closure(): void $heartbeat
     */
    public function __construct(
        public int $jobId,
        public ?EventScope $eventScope,
        public PrincipalContext $principal,
        public array $payload,
        public int $attemptNumber,
        private Closure $heartbeat,
    ) {
    }

    /** Long-running handlers call this between bounded units of restart-safe work. */
    public function heartbeat(): void
    {
        ($this->heartbeat)();
    }
}
