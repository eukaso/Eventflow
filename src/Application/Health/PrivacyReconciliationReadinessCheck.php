<?php

namespace EventFlow\Application\Health;

final readonly class PrivacyReconciliationReadinessCheck implements ReadinessCheck
{
    public function __construct(private PrivacyReconciliationGate $gate)
    {
    }

    public function identifier(): string
    {
        return 'privacy_reconciliation';
    }

    public function impact(): CheckImpact
    {
        return CheckImpact::CORE_READINESS;
    }

    public function check(): ReadinessCheckResult
    {
        return $this->gate->isReconciled()
            ? ReadinessCheckResult::up($this->identifier(), $this->impact())
            : ReadinessCheckResult::down(
                $this->identifier(),
                $this->impact(),
                HealthCode::PRIVACY_RECONCILIATION_REQUIRED,
            );
    }
}
