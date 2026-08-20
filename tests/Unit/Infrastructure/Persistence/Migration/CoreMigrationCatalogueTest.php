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

        self::assertCount(15, $definitions);
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
        self::assertSame('0006_privacy_retention', $definitions[5]->key);
        self::assertSame(5, $definitions[5]->fromSchemaVersion);
        self::assertSame(6, $definitions[5]->toSchemaVersion);
        self::assertSame('0007_event_revision', $definitions[6]->key);
        self::assertSame(6, $definitions[6]->fromSchemaVersion);
        self::assertSame(7, $definitions[6]->toSchemaVersion);
        self::assertSame('0008_venue_configuration_revisions', $definitions[7]->key);
        self::assertSame(7, $definitions[7]->fromSchemaVersion);
        self::assertSame(8, $definitions[7]->toSchemaVersion);
        self::assertSame('0009_invitation_revision', $definitions[8]->key);
        self::assertSame(8, $definitions[8]->fromSchemaVersion);
        self::assertSame(9, $definitions[8]->toSchemaVersion);
        self::assertSame('0010_seating_resource_revisions', $definitions[9]->key);
        self::assertSame(9, $definitions[9]->fromSchemaVersion);
        self::assertSame(10, $definitions[9]->toSchemaVersion);
        self::assertSame('0011_seating_recommendations', $definitions[10]->key);
        self::assertSame(10, $definitions[10]->fromSchemaVersion);
        self::assertSame(11, $definitions[10]->toSchemaVersion);
        self::assertSame('0012_communication_template_revision', $definitions[11]->key);
        self::assertSame(11, $definitions[11]->fromSchemaVersion);
        self::assertSame(12, $definitions[11]->toSchemaVersion);
        self::assertSame('0013_campaign_revision', $definitions[12]->key);
        self::assertSame(12, $definitions[12]->fromSchemaVersion);
        self::assertSame(13, $definitions[12]->toSchemaVersion);
        self::assertSame('0014_message_revision', $definitions[13]->key);
        self::assertSame(13, $definitions[13]->fromSchemaVersion);
        self::assertSame(14, $definitions[13]->toSchemaVersion);
        self::assertSame('0015_import_administration', $definitions[14]->key);
        self::assertSame(14, $definitions[14]->fromSchemaVersion);
        self::assertSame(15, $definitions[14]->toSchemaVersion);

        $entryPoint = file_get_contents($this->databaseDirectory . '/../eventflow.php');
        self::assertIsString($entryPoint);
        self::assertMatchesRegularExpression(
            "/define\\('EVENTFLOW_SCHEMA_VERSION', 15\\);/",
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

    public function testPrivacyMigrationAddsForwardOnlyActionsTombstonesAndHolds(): void
    {
        $sql = implode("\n", $this->catalogue()->definitions()[5]->statements);
        self::assertStringContainsString('CREATE TABLE wp_eventflow_privacy_actions', $sql);
        self::assertStringContainsString('CREATE TABLE wp_eventflow_privacy_states', $sql);
        self::assertStringContainsString('CREATE TABLE wp_eventflow_retention_holds', $sql);
        self::assertStringContainsString('reconciliation_status', $sql);
        self::assertStringNotContainsString('DROP TABLE', $sql);
    }

    public function testEventRevisionMigrationAddsOnlyForwardConcurrencyState(): void
    {
        $sql = implode("\n", $this->catalogue()->definitions()[6]->statements);
        self::assertStringContainsString('ADD COLUMN event_revision', $sql);
        self::assertStringContainsString('CHECK (event_revision >= 1)', $sql);
        self::assertStringNotContainsString('DROP ', $sql);
        self::assertStringNotContainsString('UPDATE ', $sql);
    }

    public function testVenueConfigurationRevisionMigrationIsForwardOnly(): void
    {
        $sql = implode("\n", $this->catalogue()->definitions()[7]->statements);
        self::assertStringContainsString('ADD COLUMN venue_revision', $sql);
        self::assertStringContainsString('ADD COLUMN configuration_revision', $sql);
        self::assertStringNotContainsString('DROP ', $sql);
        self::assertStringNotContainsString('UPDATE ', $sql);
    }

    public function testSeatingResourceRevisionMigrationIsForwardOnly(): void
    {
        $sql = implode("\n", $this->catalogue()->definitions()[9]->statements);
        foreach (['ADD COLUMN table_revision', 'ADD COLUMN seat_revision', 'ADD COLUMN group_revision'] as $column) {
            self::assertStringContainsString($column, $sql);
        }
        self::assertStringNotContainsString('DROP ', $sql);
        self::assertStringNotContainsString('UPDATE ', $sql);
    }

    public function testSeatingRecommendationMigrationIsNormalizedAndForwardOnly(): void
    {
        $sql = implode("\n", $this->catalogue()->definitions()[10]->statements);
        foreach (['CREATE TABLE wp_eventflow_seating_recommendations', 'CREATE TABLE wp_eventflow_seating_recommendation_placements', 'CREATE TABLE wp_eventflow_seating_recommendation_warnings'] as $table) self::assertStringContainsString($table, $sql);
        self::assertStringNotContainsString('JSON', strtoupper($sql));
        self::assertStringNotContainsString('DROP ', $sql);
        self::assertStringNotContainsString('UPDATE wp_eventflow_', $sql);
    }

    public function testCommunicationTemplateRevisionMigrationIsForwardOnly(): void
    {
        $sql = implode("\n", $this->catalogue()->definitions()[11]->statements);
        self::assertStringContainsString('ADD COLUMN template_revision', $sql);
        self::assertStringContainsString('CHECK (template_revision >= 1)', $sql);
        self::assertStringNotContainsString('DROP ', $sql);
        self::assertStringNotContainsString('UPDATE ', $sql);
    }

    public function testCampaignRevisionMigrationIsForwardOnly(): void
    {
        $sql = implode("\n", $this->catalogue()->definitions()[12]->statements);
        self::assertStringContainsString('ADD COLUMN campaign_revision', $sql);
        self::assertStringContainsString('CHECK (campaign_revision >= 1)', $sql);
        self::assertStringNotContainsString('DROP ', $sql);
        self::assertStringNotContainsString('UPDATE ', $sql);
    }

    public function testMessageRevisionMigrationIsForwardOnly(): void
    {
        $sql = implode("\n", $this->catalogue()->definitions()[13]->statements);
        self::assertStringContainsString('ADD COLUMN message_revision', $sql);
        self::assertStringContainsString('CHECK (message_revision >= 1)', $sql);
        self::assertStringNotContainsString('DROP ', $sql);
        self::assertStringNotContainsString('UPDATE ', $sql);
    }

    public function testImportAdministrationMigrationIsForwardOnly(): void
    {
        $sql = implode("\n", $this->catalogue()->definitions()[14]->statements);
        self::assertStringContainsString('ADD COLUMN import_revision', $sql);
        self::assertStringContainsString('ADD COLUMN cancelled_at', $sql);
        self::assertStringContainsString('CHECK (import_revision >= 1)', $sql);
        self::assertStringNotContainsString('DROP ', $sql);
        self::assertStringNotContainsString('UPDATE ', $sql);
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
