<?php

namespace EventFlow\Infrastructure\Job;

use EventFlow\Application\Job\JobException;
use EventFlow\Application\Job\JobExecutionContext;
use EventFlow\Application\Job\JobHandler;
use EventFlow\Application\Privacy\PrivacyService;

final readonly class PrivacyExecuteJobHandler implements JobHandler
{
    public function __construct(private PrivacyService $privacy) {}

    public function jobType(): string { return 'privacy.execute'; }
    public function payloadVersion(): int { return 1; }

    public function validatePayload(array $payload): void
    {
        if (array_keys($payload) !== ['privacy_action_id', 'policy_version']
            || !is_int($payload['privacy_action_id']) || $payload['privacy_action_id'] < 1
            || !is_string($payload['policy_version'])
            || preg_match('/^[A-Za-z0-9][A-Za-z0-9._-]{0,63}$/', $payload['policy_version']) !== 1
        ) {
            throw new JobException('privacy_job_payload_invalid');
        }
    }

    public function handle(JobExecutionContext $context): void
    {
        $this->validatePayload($context->payload);
        if ($context->eventScope === null) throw new JobException('privacy_job_scope_required');
        $context->heartbeat();
        $this->privacy->execute(JobContextRecord::create($context, $this->jobType(), $this->payloadVersion()));
    }
}
