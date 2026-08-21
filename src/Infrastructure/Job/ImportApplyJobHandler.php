<?php

namespace EventFlow\Infrastructure\Job;

use EventFlow\Application\Import\ImportService;
use EventFlow\Application\Import\ImportStatus;
use EventFlow\Application\Job\JobException;
use EventFlow\Application\Job\JobExecutionContext;
use EventFlow\Application\Job\JobHandler;

final readonly class ImportApplyJobHandler implements JobHandler
{
    public function __construct(private ImportService $imports) {}

    public function jobType(): string { return 'import.apply'; }
    public function payloadVersion(): int { return 1; }

    public function validatePayload(array $payload): void
    {
        if (array_keys($payload) !== ['import_job_id'] || !is_int($payload['import_job_id']) || $payload['import_job_id'] < 1) {
            throw new JobException('import_job_payload_invalid');
        }
    }

    public function handle(JobExecutionContext $context): void
    {
        $this->validatePayload($context->payload);
        $scope = $context->eventScope ?? throw new JobException('import_job_scope_required');
        for ($batch = 0; $batch < 1000; $batch++) {
            $result = $this->imports->applyBatch(
                $context->principal,
                $scope,
                $context->payload['import_job_id'],
                'durable-job-' . $context->jobId,
                100,
            );
            if ($result->job->status === ImportStatus::COMPLETED) return;
            if ($result->processedRows === 0) throw new JobException('import_worker_stalled');
            $context->heartbeat();
        }
        throw new JobException('import_worker_batch_limit');
    }
}
