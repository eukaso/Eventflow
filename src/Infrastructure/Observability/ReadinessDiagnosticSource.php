<?php

namespace EventFlow\Infrastructure\Observability;

use Throwable;
use EventFlow\Application\Health\{HealthCode, ReadinessCheck};
use EventFlow\Application\Observability\{DiagnosticSource, ObservabilityException};

final readonly class ReadinessDiagnosticSource implements DiagnosticSource
{
    /** @var list<ReadinessCheck> */
    private array $checks;

    /** @param iterable<ReadinessCheck> $checks */
    public function __construct(iterable $checks)
    {
        $items = [];
        foreach ($checks as $check) {
            if (!$check instanceof ReadinessCheck) {
                throw new ObservabilityException('diagnostic_source_invalid');
            }
            $items[] = $check;
        }
        $this->checks = $items;
    }

    public function identifier(): string
    {
        return 'readiness';
    }

    public function snapshot(): array
    {
        $results = [];
        foreach ($this->checks as $check) {
            try {
                $result = $check->check();
                $results[] = ['id' => $check->identifier(), 'impact' => $check->impact()->value, 'status' => $result->status->value, 'code' => $result->code->value];
            } catch (Throwable) {
                $results[] = ['id' => $check->identifier(), 'impact' => $check->impact()->value, 'status' => 'down', 'code' => HealthCode::CHECK_FAILED->value];
            }
        }
        return ['checks' => $results];
    }
}
