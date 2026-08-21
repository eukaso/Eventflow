<?php

namespace EventFlow\Tests\Unit\Infrastructure\Deployment;

use EventFlow\Application\Migration\MigrationDefinition;
use EventFlow\Application\Migration\MigrationRecord;
use EventFlow\Application\Migration\MigrationRepository;
use EventFlow\Infrastructure\Deployment\WpdbDeploymentSchemaVerifier;
use EventFlow\Infrastructure\Persistence\TableName;
use EventFlow\Infrastructure\Persistence\WordPress\WpdbAdapter;
use EventFlow\Infrastructure\Persistence\WordPress\WpdbTableNames;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class WpdbDeploymentSchemaVerifierTest extends TestCase
{
    public function testFreshInstallRequiresEveryEventFlowTableToBeAbsent(): void
    {
        $database = new SchemaVerifierWpdb(false);
        $verifier = $this->verifier($database, new SchemaVerifierMigrationRepository());
        $verifier->assertFreshInstall();
        self::assertCount(count(TableName::cases()), $database->queries);
    }

    public function testFreshInstallFailsWhenAnyEventFlowTableExists(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('fresh_install_eventflow_tables_present');
        $this->verifier(new SchemaVerifierWpdb(true), new SchemaVerifierMigrationRepository())
            ->assertFreshInstall();
    }

    public function testCompletedLedgerAndPhysicalTablesVerifyTogether(): void
    {
        $definition = new MigrationDefinition('0001_test', 'v1', 0, 1, 'Test migration', ['SELECT 1']);
        $repository = new SchemaVerifierMigrationRepository(1, new MigrationRecord(
            $definition->key,
            'completed',
            $definition->checksum(),
            1,
        ));

        $result = $this->verifier(new SchemaVerifierWpdb(true), $repository)->verify([$definition], 1);

        self::assertSame(1, $result->schemaVersion);
        self::assertSame(1, $result->migrationCount);
        self::assertSame(count(TableName::cases()), $result->tableCount);
    }

    private function verifier(SchemaVerifierWpdb $wpdb, MigrationRepository $repository): WpdbDeploymentSchemaVerifier
    {
        $adapter = new WpdbAdapter($wpdb);
        return new WpdbDeploymentSchemaVerifier($adapter, new WpdbTableNames('wp_'), $repository);
    }
}

final class SchemaVerifierWpdb
{
    public string $prefix = 'wp_';
    public string $last_error = '';
    /** @var list<string> */
    public array $queries = [];

    public function __construct(private readonly bool $tablesExist)
    {
    }

    public function prepare(string $query, mixed ...$values): string
    {
        return $query;
    }

    /** @return array{ENGINE:string,TABLE_COLLATION:string}|null */
    public function get_row(string $query, string $format): ?array
    {
        $this->queries[] = $query;
        return $this->tablesExist ? ['ENGINE' => 'InnoDB', 'TABLE_COLLATION' => 'utf8mb4_unicode_ci'] : null;
    }
}

final class SchemaVerifierMigrationRepository implements MigrationRepository
{
    public function __construct(private readonly ?int $version = null, private readonly ?MigrationRecord $record = null)
    {
    }

    public function initialize(): void
    {
    }

    public function currentSchemaVersion(): ?int
    {
        return $this->version;
    }

    public function find(string $key): ?MigrationRecord
    {
        return $this->record?->key === $key ? $this->record : null;
    }

    public function markRunning(MigrationDefinition $migration, string $executionSource): void
    {
    }

    public function markCompleted(MigrationDefinition $migration, int $durationMilliseconds): void
    {
    }

    public function markFailed(MigrationDefinition $migration, int $durationMilliseconds, string $errorCode): void
    {
    }
}
