<?php

namespace EventFlow\Application\Job;

interface JobHandler
{
    public function jobType(): string;

    public function payloadVersion(): int;

    /** @param array<string, mixed> $payload */
    public function validatePayload(array $payload): void;

    public function handle(JobExecutionContext $context): void;
}
