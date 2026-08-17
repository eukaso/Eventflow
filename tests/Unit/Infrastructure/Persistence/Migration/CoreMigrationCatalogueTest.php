<?php

namespace EventFlow\Tests\Unit\Infrastructure\Persistence\Migration;

use EventFlow\Application\Migration\MigrationException;
use EventFlow\Infrastructure\Persistence\Migration\CoreMigrationCatalogue;
use EventFlow\Infrastructure\Persistence\Migration\SqlMigrationLoader;
use PHPUnit\Framework\TestCase;

final class CoreMigrationCatalogueTest extends TestCase
{
    private string $databaseDirectory;

    protected function setUp(): void
    {
        $this->databaseDirectory = dirname(__DIR__, 5) . '/database';
    }

    public function testCatalogueIsAContiguousForwardOnlyChain(): void
    {
        $definitions = $this->catalogue()->definitions();

        self::assertCount(5, $definitions);
        self::assertSame('0001_sprint_3_baseline', $definitions[0]->key);
        self::assertSame(0, $definitions[0]->fromSchemaVersion);
        self::assertSame(1, $definitions[0]->toSchemaVersion);
        self::assertSame('0002_foundation_security_operations', $definitions[1]->key);
        self::assertSame(1, $definitions[1]->fromSchemaVersion);
        self::assertSame(2, $definitions[1]->toSchemaVersion);
        self::assertSame('0003_idempotency_return_once', $definitions[2]->key);
        self::assertSame(2, $definitions[2]->fromSchemaVersion);
        self::assertSame(3, $definitions[2]->toSchemaVersion);
        self::assertSame('0004_audit_integrity', $definitions[3]->key);
        self::assertSame(3, $definitions[3]->fromSchemaVersion);
        self::assertSame(4, $definitions[3]->toSchemaVersion);
        self::assertSame('0005_export_resources', $definitions[4]->key);
        self::assertSame(4, $definitions[4]->fromSchemaVersion);
        self::assertSame(5, $definitions[4]->toSchemaVersion);

        $entryPoint = file_get_contents($this->databaseDirectory . '/../eventflow.php');
        self::assertIsString($entryPoint);
        self::assertMatchesRegularExpression(
            "/define\\('EVENTFLOW_SCHEMA_VERSION', 5\\);/",
            $entryPoint,
        );
    }

    public function testBaselineIsLoadedWithoutEditingTheFrozenArtifact(): void
    {
        $baselinePath = $this->databaseDirectory . '/eventflow-schema-baseline-v1.0.sql';
        $before = hash_file('sha256', $baselinePath);
        $baseline = $this->catalogue()->definitions()[0];
        $after = hash_file('sha256', $baselinePath);

        self::assertSame($before, $after);
        self::assertStringContainsString(
            'CREATE TABLE IF NOT EXISTS wp_eventflow_schema_migrations',
            implode("\n", $baseline->statements),
        );
        self::assertStringNotContainsString('{prefix}', implode("\n", $baseline->statements));
    }

    public function testFoundationExtensionContainsOnlyTheApprovedMissingFamilies(): void
    {
        $sql = implode("\n", $this->catalogue()->definitions()[1]->statements);

        foreach ([
            'ADD COLUMN response_revision',
            'CREATE TABLE wp_eventflow_guest_sessions',
            'CREATE TABLE wp_eventflow_guest_link_credentials',
            'CREATE TABLE wp_eventflow_idempotency_records',
            'CREATE TABLE wp_eventflow_jobs',
            'ADD COLUMN worker_lease_token',
            'CONSTRAINT chk_idempotency_event_scope CHECK',
            'CONSTRAINT chk_job_event_scope CHECK',
        ] as $expected) {
            self::assertStringContainsString($expected, $sql);
        }

        self::assertStringNotContainsString('raw_token', strtolower($sql));
        self::assertStringNotContainsString('serialized', strtolower($sql));
    }

    public function testReturnOnceMigrationStoresMarkerButNoSecretPayload(): void
    {
        $sql = implode("\n", $this->catalogue()->definitions()[2]->statements);

        self::assertStringContainsString('ADD COLUMN sensitive_result', $sql);
        self::assertStringNotContainsString('response_body', strtolower($sql));
        self::assertStringNotContainsString('credential', strtolower($sql));
        self::assertStringNotContainsString('token', strtolower($sql));
    }

    public function testAuditIntegrityMigrationAddsVersionedChainsWithoutEditingHistory(): void
    {
        $sql = implode("\n", $this->catalogue()->definitions()[3]->statements);

        foreach ([
            'ADD COLUMN payload_schema_version',
            'ADD COLUMN canonicalization_version',
            'ADD COLUMN previous_hash',
            'ADD COLUMN record_hash',
            'CREATE TABLE wp_eventflow_audit_chain_heads',
            'CONSTRAINT chk_audit_chain_event_scope CHECK',
        ] as $expected) {
            self::assertStringContainsString($expected, $sql);
        }

        self::assertStringNotContainsString('UPDATE wp_eventflow_audit_logs', $sql);
        self::assertStringNotContainsString('DELETE FROM wp_eventflow_audit_logs', $sql);
    }

    public function testExportMigrationAddsControlledResourceWithoutArtifactContent(): void
    {
        $sql = implode("\n", $this->catalogue()->definitions()[4]->statements);
        self::assertStringContainsString('CREATE TABLE wp_eventflow_exports', $sql);
        self::assertStringContainsString('artifact_locator', $sql);
        self::assertStringContainsString('requested_at_snapshot', $sql);
        self::assertStringNotContainsString('artifact_content', $sql);
        self::assertStringNotContainsString('download_token', $sql);
    }

    public function testDatabasePrefixFailsClosed(): void
    {
        $this->expectException(MigrationException::class);
        $this->expectExceptionMessage('invalid_database_prefix');

        new SqlMigrationLoader('wp_; DROP TABLE users;');
    }

    private function catalogue(): CoreMigrationCatalogue
    {
        return new CoreMigrationCatalogue(
            $this->databaseDirectory,
            new SqlMigrationLoader('wp_'),
        );
    }
}
