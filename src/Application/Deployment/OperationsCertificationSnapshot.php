<?php

namespace EventFlow\Application\Deployment;

final readonly class OperationsCertificationSnapshot
{
    public function __construct(
        public bool $cronScheduled,
        public int $cronCadenceSeconds,
        public bool $workerCompleted,
        public bool $heartbeatRecorded,
        public bool $retryScheduled,
        public bool $retryCompleted,
        public bool $leaseRecovered,
        public bool $protectedStorageRoundTrip,
        public bool $anonymousExportDenied,
        public bool $auditIntegrity,
        public int $auditRecords,
        public bool $privacyReconciled,
        public bool $diagnosticsSanitized,
        public int $diagnosticSections,
    ) {}
}
