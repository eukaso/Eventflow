<?php

namespace EventFlow\Infrastructure\Observability;

use EventFlow\Application\Observability\MetricSink;

final readonly class WordPressMetricSink implements MetricSink
{
    public function increment(string $name, array $labels, int $value): void
    {
        if (function_exists('do_action')) {
            do_action('eventflow_metric_increment', $name, $labels, $value);
        }
    }
}
