<?php

namespace EventFlow\Application\Observability;

final readonly class MetricDefinition
{
    /** @param array<string, list<string>> $allowedLabels */
    public function __construct(public string $name, public array $allowedLabels)
    {
        if (!preg_match('/^eventflow_[a-z0-9_]{3,80}_total$/', $name)) {
            throw new ObservabilityException('metric_definition_invalid');
        }
        foreach ($allowedLabels as $label => $values) {
            if (!preg_match('/^[a-z][a-z0-9_]{0,31}$/', $label) || $values === [] || count($values) > 100) {
                throw new ObservabilityException('metric_definition_invalid');
            }
            foreach ($values as $value) {
                if (!is_string($value) || !preg_match('/^[a-z0-9][a-z0-9_.-]{0,99}$/', $value)) {
                    throw new ObservabilityException('metric_definition_invalid');
                }
            }
        }
    }
}
