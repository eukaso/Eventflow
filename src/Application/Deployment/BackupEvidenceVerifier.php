<?php

namespace EventFlow\Application\Deployment;

interface BackupEvidenceVerifier
{
    public function verify(
        string $evidencePath,
        string $artifactSha256,
        int $nowEpoch,
    ): VerifiedBackupEvidence;
}
