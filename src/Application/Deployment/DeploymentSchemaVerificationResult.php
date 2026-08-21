<?php

namespace EventFlow\Application\Deployment;

final readonly class DeploymentSchemaVerificationResult
{
    public function __construct(
        public int $schemaVersion,
        public int $migrationCount,
        public int $tableCount,
    ) {
    }
}
