<?php

namespace EventFlow\Application\Deployment;

final readonly class VerifiedBackupEvidence
{
    public function __construct(
        public string $evidenceId,
        public string $evidenceSha256,
        public string $targetEnvironment,
        public string $createdAt,
        public string $databaseSha256,
        public string $filesSha256,
        public string $restoreProcedureId,
    ) {
    }
}
