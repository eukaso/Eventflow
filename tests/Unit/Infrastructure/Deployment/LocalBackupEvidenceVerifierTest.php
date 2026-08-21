<?php

namespace EventFlow\Tests\Unit\Infrastructure\Deployment;

use EventFlow\Infrastructure\Deployment\LocalBackupEvidenceVerifier;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class LocalBackupEvidenceVerifierTest extends TestCase
{
    private string $temporary;
    private int $now = 1787270400;
    private string $artifactSha;

    protected function setUp(): void
    {
        $this->temporary = sys_get_temp_dir() . '/eventflow-backup-' . bin2hex(random_bytes(8));
        self::assertTrue(mkdir($this->temporary, 0775, true));
        $this->artifactSha = hash('sha256', 'artifact');
    }

    protected function tearDown(): void
    {
        foreach (glob($this->temporary . '/*') ?: [] as $file) {
            unlink($file);
        }
        rmdir($this->temporary);
    }

    public function testVerifiedBackupBindsArtifactArchivesAndRestoreRehearsal(): void
    {
        $evidencePath = $this->evidence();

        $verified = (new LocalBackupEvidenceVerifier())->verify($evidencePath, $this->artifactSha, $this->now);

        self::assertSame('backup-20260820-001', $verified->evidenceId);
        self::assertSame(hash_file('sha256', $evidencePath), $verified->evidenceSha256);
        self::assertSame(hash('sha256', 'database-backup'), $verified->databaseSha256);
        self::assertSame(hash('sha256', 'files-backup'), $verified->filesSha256);
        self::assertSame('restore-runbook-001', $verified->restoreProcedureId);
    }

    public function testTamperedArchiveFailsVerification(): void
    {
        $evidencePath = $this->evidence();
        file_put_contents($this->temporary . '/database.sql.gz', 'tampered');

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('backup_archive_invalid');
        (new LocalBackupEvidenceVerifier())->verify($evidencePath, $this->artifactSha, $this->now);
    }

    public function testRestoreEvidenceMustMatchTheVerifiedArchives(): void
    {
        $evidencePath = $this->evidence(databaseRestoreSha: hash('sha256', 'other'));

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('backup_restore_rehearsal_mismatch');
        (new LocalBackupEvidenceVerifier())->verify($evidencePath, $this->artifactSha, $this->now);
    }

    public function testStaleBackupCannotAuthorizeMigration(): void
    {
        $evidencePath = $this->evidence(createdEpoch: $this->now - 86401);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('backup_evidence_stale_or_invalid');
        (new LocalBackupEvidenceVerifier())->verify($evidencePath, $this->artifactSha, $this->now);
    }

    private function evidence(?string $databaseRestoreSha = null, ?int $createdEpoch = null): string
    {
        $database = $this->temporary . '/database.sql.gz';
        $files = $this->temporary . '/files.tar.gz';
        file_put_contents($database, 'database-backup');
        file_put_contents($files, 'files-backup');
        $databaseSha = hash_file('sha256', $database);
        $filesSha = hash_file('sha256', $files);
        self::assertIsString($databaseSha);
        self::assertIsString($filesSha);
        $createdEpoch ??= $this->now - 300;
        $evidence = [
            'format_version' => 1,
            'evidence_id' => 'backup-20260820-001',
            'target_environment' => 'production',
            'artifact_sha256' => $this->artifactSha,
            'created_at' => gmdate('Y-m-d\TH:i:s\Z', $createdEpoch),
            'restore_procedure_id' => 'restore-runbook-001',
            'database_backup' => ['path' => $database, 'bytes' => filesize($database), 'sha256' => $databaseSha],
            'files_backup' => ['path' => $files, 'bytes' => filesize($files), 'sha256' => $filesSha],
            'restore_rehearsal' => [
                'status' => 'passed',
                'completed_at' => gmdate('Y-m-d\TH:i:s\Z', $createdEpoch + 60),
                'database_sha256' => $databaseRestoreSha ?? $databaseSha,
                'database_bytes' => filesize($database),
                'files_sha256' => $filesSha,
                'files_bytes' => filesize($files),
            ],
        ];
        $path = $this->temporary . '/evidence.json';
        file_put_contents($path, json_encode($evidence, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));
        return $path;
    }
}
