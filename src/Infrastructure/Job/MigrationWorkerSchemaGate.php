<?php

namespace EventFlow\Infrastructure\Job;

use EventFlow\Application\Job\JobException;
use EventFlow\Application\Job\WorkerSchemaGate;
use EventFlow\Application\Migration\MigrationRepository;
use EventFlow\Bootstrap\SchemaCompatibility;
use EventFlow\Bootstrap\SchemaCompatibilityChecker;

final readonly class MigrationWorkerSchemaGate implements WorkerSchemaGate
{
    public function __construct(
        private MigrationRepository $migrations,
        private SchemaCompatibilityChecker $checker,
        private int $expectedSchemaVersion,
    ) {
    }

    public function assertCompatible(): void
    {
        if ($this->checker->check(
            $this->expectedSchemaVersion,
            $this->migrations->currentSchemaVersion(),
        ) !== SchemaCompatibility::COMPATIBLE) {
            throw new JobException('job_worker_schema_incompatible');
        }
    }
}
