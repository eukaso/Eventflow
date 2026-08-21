<?php

namespace EventFlow\Application\Deployment;

use InvalidArgumentException;

final readonly class ReferenceDataReconciliationService
{
    public function evaluate(ReferenceDataSnapshot $snapshot, int $expectedInvitations): ReferenceDataReconciliationReport
    {
        if ($expectedInvitations < 1 || $expectedInvitations > 10000) throw new InvalidArgumentException('reference_expected_count_invalid');
        $totalsMatch = $snapshot->sourceInvitations === $snapshot->targetInvitations
            && $snapshot->sourceCapacity === $snapshot->targetCapacity
            && $snapshot->sourceAccepted === $snapshot->targetAccepted
            && $snapshot->sourcePending === $snapshot->targetPending
            && $snapshot->sourceDeclined === $snapshot->targetDeclined
            && $snapshot->sourceCompanions === $snapshot->targetCompanions;
        $checks = [
            $this->check('source_integrity', $snapshot->sourceValid && preg_match('/^[a-f0-9]{64}$/', $snapshot->sourceFingerprint) === 1, 'reference_source_invalid'),
            $this->check('expected_inventory', $snapshot->sourceInvitations === $expectedInvitations, 'reference_invitation_count_mismatch'),
            $this->check('import_completion', $snapshot->importStatus === 'completed' && $snapshot->importRows === $expectedInvitations && $snapshot->importApplied === $expectedInvitations && $snapshot->importFailed === 0, 'reference_import_incomplete'),
            $this->check('aggregate_reconciliation', $totalsMatch, 'reference_totals_mismatch'),
            $this->check('row_reconciliation', $snapshot->matchedRows === $expectedInvitations && $snapshot->mismatchedRows === 0 && $snapshot->orphanRows === 0, 'reference_rows_mismatch'),
            $this->check('rollback_preservation', $snapshot->legacyTablePreserved, 'legacy_reference_table_missing'),
        ];
        return new ReferenceDataReconciliationReport($expectedInvitations, $snapshot, $checks);
    }

    private function check(string $identifier, bool $passed, string $failureCode): ReferenceDataReconciliationCheck
    {
        return new ReferenceDataReconciliationCheck($identifier, $passed ? ReferenceDataReconciliationCheck::PASS : ReferenceDataReconciliationCheck::FAIL, $passed ? 'ok' : $failureCode);
    }
}
