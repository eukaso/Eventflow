<?php

namespace EventFlow\Tests\Integration;

use PHPUnit\Framework\TestCase;

final class Sprint12BackupMigrationValidationTest extends TestCase
{
    public function testBackupEvidenceBindsArtifactArchivesFreshnessAndRestore(): void
    {
        $verifier = $this->source('src/Infrastructure/Deployment/LocalBackupEvidenceVerifier.php');
        foreach (['artifact_sha256', 'database_backup', 'files_backup', 'restore_rehearsal', 'MAXIMUM_AGE_SECONDS', "hash_file('sha256'", 'is_link'] as $expected) {
            self::assertStringContainsString($expected, $verifier);
        }
        foreach (['password', 'DB_PASSWORD', 'AUTH_KEY'] as $forbidden) {
            self::assertStringNotContainsString($forbidden, $verifier);
        }
    }

    public function testMigrationSurfaceIsExplicitFreshOnlyAndBackupGated(): void
    {
        $tool = $this->source('tools/run-deployment-migrations.php');
        foreach (['--confirm-fresh-install', '--backup-evidence=', '--artifact-sha256=', "run(\$definitions, 'deployment')", 'assertFreshInstall', 'EVENTFLOW_SCHEMA_VERSION'] as $expected) {
            self::assertStringContainsString($expected, $tool);
        }
        self::assertStringContainsString("preg_match('/^--([a-z0-9-]+)=(.*)\$/'", $tool);
        self::assertStringNotContainsString('register_activation_hook', $tool);
        self::assertStringNotContainsString('DROP TABLE', $tool);
    }

    public function testPostMigrationVerificationCoversLedgerTablesEngineAndCharset(): void
    {
        $verifier = $this->source('src/Infrastructure/Deployment/WpdbDeploymentSchemaVerifier.php');
        foreach (['currentSchemaVersion', 'TableName::cases()', 'hash_equals', 'information_schema.TABLES', "'innodb'", "'utf8mb4_'"] as $expected) {
            self::assertStringContainsString($expected, $verifier);
        }
    }

    public function testLiveEvidenceRemainsBlockedAndLegacyDataIsNotDeleted(): void
    {
        $report = $this->source('docs/10-testing/Sprint-12-Backup-Migration-Acceptance-Report.md');
        self::assertStringContainsString('BLOCKED', $report);
        self::assertStringNotContainsString('DreamHost backup/restore rehearsal: PASS', $report);
        $runbook = $this->source('docs/09-developer-guide/Sprint-12-Backup-Migration-and-Rollback.md');
        self::assertStringContainsString('must not be deleted', $runbook);
        self::assertStringContainsString('IMP-095', $this->source('CHANGELOG.md'));
    }

    private function source(string $path): string
    {
        $source = file_get_contents(dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $path));
        self::assertIsString($source, $path);
        return $source;
    }
}
