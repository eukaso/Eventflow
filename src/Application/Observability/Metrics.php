<?php

namespace EventFlow\Application\Observability;

final class Metrics
{
    /** @var array<string, MetricDefinition> */
    private array $definitions = [];

    /** @param iterable<MetricDefinition> $definitions */
    public function __construct(private readonly MetricSink $sink, iterable $definitions)
    {
        foreach ($definitions as $definition) {
            if (!$definition instanceof MetricDefinition || isset($this->definitions[$definition->name])) {
                throw new ObservabilityException('metric_definition_invalid');
            }
            $this->definitions[$definition->name] = $definition;
        }
    }

    /** @param array<string, string> $labels */
    public function increment(string $name, array $labels, int $value = 1): void
    {
        $definition = $this->definitions[$name] ?? throw new ObservabilityException('metric_not_registered');
        if ($value < 1 || array_keys($labels) !== array_keys($definition->allowedLabels)) {
            throw new ObservabilityException('metric_labels_invalid');
        }
        foreach ($labels as $label => $labelValue) {
            if (!in_array($labelValue, $definition->allowedLabels[$label], true)) {
                throw new ObservabilityException('metric_labels_invalid');
            }
        }
        $this->sink->increment($name, $labels, $value);
    }
}
