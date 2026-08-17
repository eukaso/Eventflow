<?php

namespace EventFlow\Application\Observability;

interface MetricSink
{
    /** @param array<string, string> $labels */
    public function increment(string $name, array $labels, int $value): void;
}
