<?php

namespace EventFlow\Application\Health;

use DateTimeImmutable;

final readonly class ReadinessReport
{
    /** @param list<ReadinessCheckResult> $checks */
    public function __construct(
        public OperationalStatus $status,
        public bool $healthy,
        public bool $ready,
        public DateTimeImmutable $generatedAt,
        public string $applicationVersion,
        public array $checks,
    ) {
    }
}
