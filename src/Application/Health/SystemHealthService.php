<?php

namespace EventFlow\Application\Health;

use DateTimeZone;
use EventFlow\Application\Clock\Clock;
use EventFlow\Bootstrap\BootstrapResult;
use InvalidArgumentException;
use Throwable;

final class SystemHealthService
{
    /** @var list<ReadinessCheck> */
    private array $checks;

    /** @param iterable<ReadinessCheck> $checks */
    public function __construct(
        private readonly BootstrapResult $bootstrap,
        iterable $checks,
        private readonly Clock $clock,
        private readonly string $applicationVersion,
    ) {
        if ($applicationVersion === '' || strlen($applicationVersion) > 50) {
            throw new InvalidArgumentException('invalid_health_application_version');
        }
        $this->checks = [];
        $identifiers = [];
        $coreCheckCount = 0;
        foreach ($checks as $check) {
            if (!$check instanceof ReadinessCheck) {
                throw new InvalidArgumentException('invalid_readiness_check');
            }
            $identifier = $check->identifier();
            if (!preg_match('/^[a-z][a-z0-9_]{2,63}$/', $identifier) || isset($identifiers[$identifier])) {
                throw new InvalidArgumentException('invalid_or_duplicate_readiness_check');
            }
            $identifiers[$identifier] = true;
            if ($check->impact() === CheckImpact::CORE_READINESS) {
                $coreCheckCount++;
            }
            $this->checks[] = $check;
        }
        if ($coreCheckCount === 0) {
            throw new InvalidArgumentException('core_readiness_check_required');
        }
    }

    public function health(): HealthReport
    {
        return new HealthReport(
            $this->bootstrap->healthy ? OperationalStatus::HEALTHY : OperationalStatus::UNAVAILABLE,
            $this->bootstrap->healthy,
            $this->now(),
            $this->applicationVersion,
            $this->bootstrap->healthy ? HealthCode::OK : $this->bootstrapCode(),
        );
    }

    public function readiness(): ReadinessReport
    {
        if (!$this->bootstrap->ready) {
            return new ReadinessReport(
                OperationalStatus::UNAVAILABLE,
                $this->bootstrap->healthy,
                false,
                $this->now(),
                $this->applicationVersion,
                [ReadinessCheckResult::down(
                    'bootstrap',
                    CheckImpact::CORE_READINESS,
                    $this->bootstrapCode(),
                )],
            );
        }

        $results = [];
        $coreReady = true;
        $optionalDegraded = false;
        foreach ($this->checks as $check) {
            try {
                $result = $check->check();
                if ($result->identifier !== $check->identifier() || $result->impact !== $check->impact()) {
                    throw new InvalidArgumentException('readiness_check_contract_mismatch');
                }
            } catch (Throwable) {
                $result = ReadinessCheckResult::down(
                    $check->identifier(),
                    $check->impact(),
                    HealthCode::CHECK_FAILED,
                );
            }
            $results[] = $result;
            if ($result->status !== CheckStatus::UP) {
                if ($result->impact === CheckImpact::CORE_READINESS) {
                    $coreReady = false;
                } else {
                    $optionalDegraded = true;
                }
            }
        }

        $status = !$coreReady
            ? OperationalStatus::UNAVAILABLE
            : ($optionalDegraded ? OperationalStatus::DEGRADED : OperationalStatus::HEALTHY);

        return new ReadinessReport(
            $status,
            $this->bootstrap->healthy,
            $coreReady,
            $this->now(),
            $this->applicationVersion,
            $results,
        );
    }

    private function bootstrapCode(): HealthCode
    {
        return match ($this->bootstrap->state) {
            \EventFlow\Bootstrap\BootstrapState::MIGRATION_REQUIRED => HealthCode::SCHEMA_MIGRATION_REQUIRED,
            \EventFlow\Bootstrap\BootstrapState::INCOMPATIBLE_SCHEMA => HealthCode::APPLICATION_SCHEMA_INCOMPATIBLE,
            \EventFlow\Bootstrap\BootstrapState::FAILED => HealthCode::BOOTSTRAP_FAILURE,
            default => HealthCode::BOOTSTRAP_UNAVAILABLE,
        };
    }

    private function now(): \DateTimeImmutable
    {
        return $this->clock->now()->setTimezone(new DateTimeZone('UTC'));
    }
}
