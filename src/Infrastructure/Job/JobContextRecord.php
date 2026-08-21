<?php

namespace EventFlow\Infrastructure\Job;

use EventFlow\Application\Job\JobExecutionContext;
use EventFlow\Application\Job\JobRecord;
use EventFlow\Application\Job\JobStatus;

final class JobContextRecord
{
    public static function create(JobExecutionContext $context, string $type, int $version): JobRecord
    {
        return new JobRecord(
            $context->jobId,
            $context->eventScope,
            $type,
            $version,
            $context->payload,
            $context->principal->committedCapabilities,
            JobStatus::RUNNING,
            0,
            $context->attemptNumber,
            max(1, $context->attemptNumber),
        );
    }
}
