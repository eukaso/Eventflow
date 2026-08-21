<?php

namespace EventFlow\Application\Deployment;

final readonly class ReferenceDataSnapshot
{
    public function __construct(
        public string $sourceFingerprint,
        public bool $sourceValid,
        public bool $legacyTablePreserved,
        public int $sourceInvitations,
        public int $sourceCapacity,
        public int $sourceAccepted,
        public int $sourcePending,
        public int $sourceDeclined,
        public int $sourceCompanions,
        public string $importStatus,
        public int $importRows,
        public int $importApplied,
        public int $importFailed,
        public int $targetInvitations,
        public int $targetCapacity,
        public int $targetAccepted,
        public int $targetPending,
        public int $targetDeclined,
        public int $targetCompanions,
        public int $matchedRows,
        public int $mismatchedRows,
        public int $orphanRows,
    ) {
    }
}
