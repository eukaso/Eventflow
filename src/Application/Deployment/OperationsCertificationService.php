<?php

namespace EventFlow\Application\Deployment;

final readonly class OperationsCertificationService
{
    public function evaluate(OperationsCertificationSnapshot $snapshot): OperationsCertificationReport
    {
        return new OperationsCertificationReport($snapshot, [
            $this->check('cron_cadence', $snapshot->cronScheduled && $snapshot->cronCadenceSeconds >= 60 && $snapshot->cronCadenceSeconds <= 300, 'operations_cron_invalid'),
            $this->check('worker_completion', $snapshot->workerCompleted, 'operations_worker_failed'),
            $this->check('worker_heartbeat', $snapshot->heartbeatRecorded, 'operations_heartbeat_failed'),
            $this->check('retry_backoff', $snapshot->retryScheduled && $snapshot->retryCompleted, 'operations_retry_failed'),
            $this->check('lease_recovery', $snapshot->leaseRecovered, 'operations_lease_recovery_failed'),
            $this->check('protected_storage', $snapshot->protectedStorageRoundTrip, 'operations_storage_failed'),
            $this->check('authenticated_download', $snapshot->anonymousExportDenied, 'operations_export_authorization_failed'),
            $this->check('audit_integrity', $snapshot->auditIntegrity, 'operations_audit_integrity_failed'),
            $this->check('privacy_reconciliation', $snapshot->privacyReconciled, 'operations_privacy_unreconciled'),
            $this->check('sanitized_diagnostics', $snapshot->diagnosticsSanitized, 'operations_diagnostics_unsafe'),
        ]);
    }

    private function check(string $identifier, bool $passed, string $failure): OperationsCertificationCheck
    {
        return new OperationsCertificationCheck($identifier, $passed ? 'pass' : 'fail', $passed ? 'ok' : $failure);
    }
}
