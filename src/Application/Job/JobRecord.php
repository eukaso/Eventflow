<?php

namespace EventFlow\Application\Job;

use EventFlow\Application\Authorization\Capability;
use EventFlow\Application\Authorization\PrincipalContext;
use EventFlow\Application\Persistence\EventScope;

final readonly class JobRecord
{
    /** @param array<string, mixed> $payload @param list<Capability> $committedCapabilities */
    public function __construct(
        public int $jobId,
        public ?EventScope $eventScope,
        public string $jobType,
        public int $payloadVersion,
        public array $payload,
        public array $committedCapabilities,
        public JobStatus $status,
        public int $priority,
        public int $attemptCount,
        public int $maxAttempts,
    ) {
    }

    public function principal(): PrincipalContext
    {
        return PrincipalContext::backgroundJob($this->jobId, $this->eventScope, $this->committedCapabilities);
    }
}
