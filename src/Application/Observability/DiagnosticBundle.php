<?php

namespace EventFlow\Application\Observability;

use DateTimeImmutable;

final readonly class DiagnosticBundle
{
    /** @param array<string, array<string, mixed>> $sections */
    public function __construct(
        public string $requestId,
        public int $eventId,
        public DateTimeImmutable $generatedAt,
        public array $sections,
    ) {
        if ($eventId < 1 || $sections === []) {
            throw new ObservabilityException('diagnostic_bundle_invalid');
        }
    }
}
