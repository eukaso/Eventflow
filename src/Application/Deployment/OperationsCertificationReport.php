<?php

namespace EventFlow\Application\Deployment;

final readonly class OperationsCertificationReport
{
    /** @param list<OperationsCertificationCheck> $checks */
    public function __construct(public OperationsCertificationSnapshot $snapshot, public array $checks) {}

    public function passed(): bool
    {
        foreach ($this->checks as $check) if ($check->status !== 'pass') return false;
        return true;
    }

    public function toArray(): array
    {
        return [
            'status' => $this->passed() ? 'pass' : 'blocked',
            'metrics' => [
                'cron_cadence_seconds' => $this->snapshot->cronCadenceSeconds,
                'audit_records_verified' => $this->snapshot->auditRecords,
                'diagnostic_sections' => $this->snapshot->diagnosticSections,
            ],
            'checks' => array_map(static fn (OperationsCertificationCheck $check): array => $check->toArray(), $this->checks),
        ];
    }
}
