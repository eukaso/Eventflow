<?php

namespace EventFlow\Infrastructure\Health;

use EventFlow\Application\Health\{CheckImpact, HealthCode, ReadinessCheck, ReadinessCheckResult};
use EventFlow\Bootstrap\{BootstrapResult, BootstrapState};

final readonly class BootstrapReadinessCheck implements ReadinessCheck
{
    public function __construct(private BootstrapResult $bootstrap)
    {
    }

    public function identifier(): string { return 'bootstrap'; }
    public function impact(): CheckImpact { return CheckImpact::CORE_READINESS; }

    public function check(): ReadinessCheckResult
    {
        if ($this->bootstrap->ready) {
            return ReadinessCheckResult::up($this->identifier(), $this->impact());
        }
        $code = match ($this->bootstrap->state) {
            BootstrapState::MIGRATION_REQUIRED => HealthCode::SCHEMA_MIGRATION_REQUIRED,
            BootstrapState::INCOMPATIBLE_SCHEMA => HealthCode::APPLICATION_SCHEMA_INCOMPATIBLE,
            BootstrapState::FAILED => HealthCode::BOOTSTRAP_FAILURE,
            default => HealthCode::BOOTSTRAP_UNAVAILABLE,
        };
        return ReadinessCheckResult::down($this->identifier(), $this->impact(), $code);
    }
}
