<?php

namespace EventFlow\Infrastructure\Job;

use EventFlow\Application\Export\ExportFormat;
use EventFlow\Application\Export\ExportService;
use EventFlow\Application\Export\ExportType;
use EventFlow\Application\Job\JobException;
use EventFlow\Application\Job\JobExecutionContext;
use EventFlow\Application\Job\JobHandler;

final readonly class ExportGenerateJobHandler implements JobHandler
{
    public function __construct(private ExportService $exports) {}

    public function jobType(): string { return 'export.generate'; }
    public function payloadVersion(): int { return 1; }

    public function validatePayload(array $payload): void
    {
        if (array_keys($payload) !== ['export_id', 'type', 'format', 'cutoff_at']
            || !is_int($payload['export_id']) || $payload['export_id'] < 1
            || !is_string($payload['type']) || ExportType::tryFrom($payload['type']) === null
            || !is_string($payload['format']) || ExportFormat::tryFrom($payload['format']) === null
            || !is_string($payload['cutoff_at']) || strtotime($payload['cutoff_at']) === false
        ) {
            throw new JobException('export_job_payload_invalid');
        }
    }

    public function handle(JobExecutionContext $context): void
    {
        $this->validatePayload($context->payload);
        if ($context->eventScope === null) throw new JobException('export_job_scope_required');
        $context->heartbeat();
        $this->exports->generate(JobContextRecord::create($context, $this->jobType(), $this->payloadVersion()));
    }
}
