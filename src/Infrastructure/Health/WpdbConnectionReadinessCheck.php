<?php

namespace EventFlow\Infrastructure\Health;

use EventFlow\Application\Health\CheckImpact;
use EventFlow\Application\Health\HealthCode;
use EventFlow\Application\Health\ReadinessCheck;
use EventFlow\Application\Health\ReadinessCheckResult;
use EventFlow\Infrastructure\Persistence\WordPress\WpdbAdapter;
use Throwable;

final readonly class WpdbConnectionReadinessCheck implements ReadinessCheck
{
    public function __construct(private WpdbAdapter $database)
    {
    }

    public function identifier(): string
    {
        return 'database_connection';
    }

    public function impact(): CheckImpact
    {
        return CheckImpact::CORE_READINESS;
    }

    public function check(): ReadinessCheckResult
    {
        try {
            return (int) $this->database->fetchValue('SELECT 1') === 1
                ? ReadinessCheckResult::up($this->identifier(), $this->impact())
                : ReadinessCheckResult::down(
                    $this->identifier(), $this->impact(), HealthCode::DATABASE_UNAVAILABLE,
                );
        } catch (Throwable) {
            return ReadinessCheckResult::down(
                $this->identifier(), $this->impact(), HealthCode::DATABASE_UNAVAILABLE,
            );
        }
    }
}
