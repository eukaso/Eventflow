<?php

namespace EventFlow\Infrastructure\Job;

use EventFlow\Application\Job\JobException;
use EventFlow\Application\Job\JobExecutionContext;
use EventFlow\Application\Job\JobExecutionException;
use EventFlow\Application\Job\JobHandler;

final class OperationsProbeJobHandler implements JobHandler
{
    public function jobType(): string { return 'operations.probe'; }
    public function payloadVersion(): int { return 1; }

    public function validatePayload(array $payload): void
    {
        if (array_keys($payload) !== ['mode'] || !in_array($payload['mode'] ?? null, ['heartbeat', 'retry_once'], true)) {
            throw new JobException('operations_probe_payload_invalid');
        }
    }

    public function handle(JobExecutionContext $context): void
    {
        $this->validatePayload($context->payload);
        $context->heartbeat();
        if ($context->payload['mode'] === 'retry_once' && $context->attemptNumber === 1) {
            throw new JobExecutionException('operations_probe_retry', true);
        }
    }
}
