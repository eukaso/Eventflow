<?php

namespace EventFlow\Application\Deployment;

final readonly class ReferenceDataReconciliationReport
{
    /** @param list<ReferenceDataReconciliationCheck> $checks */
    public function __construct(
        public int $expectedInvitations,
        public ReferenceDataSnapshot $snapshot,
        public array $checks,
    ) {
    }

    public function passed(): bool
    {
        foreach ($this->checks as $check) {
            if ($check->status !== ReferenceDataReconciliationCheck::PASS) return false;
        }
        return true;
    }

    /** @return array<string,mixed> */
    public function toArray(): array
    {
        $source = $this->snapshot;
        return [
            'status' => $this->passed() ? 'pass' : 'blocked',
            'expected_invitations' => $this->expectedInvitations,
            'source_fingerprint' => $source->sourceFingerprint,
            'source_totals' => $this->totals($source->sourceInvitations, $source->sourceCapacity, $source->sourceAccepted, $source->sourcePending, $source->sourceDeclined, $source->sourceCompanions),
            'target_totals' => $this->totals($source->targetInvitations, $source->targetCapacity, $source->targetAccepted, $source->targetPending, $source->targetDeclined, $source->targetCompanions),
            'import' => ['status' => $source->importStatus, 'rows' => $source->importRows, 'applied' => $source->importApplied, 'failed' => $source->importFailed],
            'row_reconciliation' => ['matched' => $source->matchedRows, 'mismatched' => $source->mismatchedRows, 'orphaned' => $source->orphanRows],
            'checks' => array_map(static fn (ReferenceDataReconciliationCheck $check): array => $check->toArray(), $this->checks),
        ];
    }

    /** @return array{invitations:int,capacity:int,accepted:int,pending:int,declined:int,companions:int} */
    private function totals(int $invitations, int $capacity, int $accepted, int $pending, int $declined, int $companions): array
    {
        return compact('invitations', 'capacity', 'accepted', 'pending', 'declined', 'companions');
    }
}
