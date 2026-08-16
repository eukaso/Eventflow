<?php

namespace EventFlow\Infrastructure\Health;

use EventFlow\Application\Health\CheckImpact;
use EventFlow\Application\Health\HealthCode;
use EventFlow\Application\Health\ReadinessCheck;
use EventFlow\Application\Health\ReadinessCheckResult;
use EventFlow\Application\Migration\MigrationRepository;
use EventFlow\Bootstrap\SchemaCompatibility;
use EventFlow\Bootstrap\SchemaCompatibilityChecker;

final readonly class SchemaReadinessCheck implements ReadinessCheck
{
    public function __construct(
        private MigrationRepository $migrations,
        private SchemaCompatibilityChecker $checker,
        private int $expectedSchemaVersion,
    ) {
    }

    public function identifier(): string
    {
        return 'database_schema';
    }

    public function impact(): CheckImpact
    {
        return CheckImpact::CORE_READINESS;
    }

    public function check(): ReadinessCheckResult
    {
        return match ($this->checker->check(
            $this->expectedSchemaVersion,
            $this->migrations->currentSchemaVersion(),
        )) {
            SchemaCompatibility::COMPATIBLE => ReadinessCheckResult::up($this->identifier(), $this->impact()),
            SchemaCompatibility::MIGRATION_REQUIRED => ReadinessCheckResult::down(
                $this->identifier(), $this->impact(), HealthCode::SCHEMA_MIGRATION_REQUIRED,
            ),
            SchemaCompatibility::APPLICATION_TOO_OLD => ReadinessCheckResult::down(
                $this->identifier(), $this->impact(), HealthCode::APPLICATION_SCHEMA_INCOMPATIBLE,
            ),
            SchemaCompatibility::UNKNOWN => ReadinessCheckResult::down(
                $this->identifier(), $this->impact(), HealthCode::SCHEMA_COMPATIBILITY_UNKNOWN,
            ),
        };
    }
}
