<?php

namespace EventFlow\Application\Health;

use InvalidArgumentException;

final readonly class ReadinessCheckResult
{
    public function __construct(
        public string $identifier,
        public CheckImpact $impact,
        public CheckStatus $status,
        public HealthCode $code,
    ) {
        if (
            !preg_match('/^[a-z][a-z0-9_]{2,63}$/', $identifier)
        ) {
            throw new InvalidArgumentException('invalid_readiness_check_result');
        }
        if ($status === CheckStatus::UP && $code !== HealthCode::OK) {
            throw new InvalidArgumentException('invalid_readiness_success_code');
        }
    }

    public static function up(string $identifier, CheckImpact $impact): self
    {
        return new self($identifier, $impact, CheckStatus::UP, HealthCode::OK);
    }

    public static function degraded(string $identifier, CheckImpact $impact, HealthCode $code): self
    {
        return new self($identifier, $impact, CheckStatus::DEGRADED, $code);
    }

    public static function down(string $identifier, CheckImpact $impact, HealthCode $code): self
    {
        return new self($identifier, $impact, CheckStatus::DOWN, $code);
    }
}
