<?php

namespace EventFlow\Application\Health;

use DateTimeImmutable;

final readonly class HealthReport
{
    public function __construct(
        public OperationalStatus $status,
        public bool $healthy,
        public DateTimeImmutable $generatedAt,
        public string $applicationVersion,
        public HealthCode $code,
    ) {
    }
}
