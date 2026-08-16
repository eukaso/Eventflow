<?php

namespace EventFlow\Tests\Unit\Infrastructure\Health;

use EventFlow\Application\Health\CheckStatus;
use EventFlow\Application\Health\HealthCode;
use EventFlow\Application\Migration\MigrationDefinition;
use EventFlow\Application\Migration\MigrationRecord;
use EventFlow\Application\Migration\MigrationRepository;
use EventFlow\Bootstrap\SchemaCompatibilityChecker;
use EventFlow\Infrastructure\Health\SchemaReadinessCheck;
use EventFlow\Infrastructure\Health\WpdbConnectionReadinessCheck;
use EventFlow\Infrastructure\Persistence\WordPress\WpdbAdapter;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class ReadinessChecksTest extends TestCase
{
    #[DataProvider('schemaVersions')]
    public function testSchemaCompatibilityIsReportedWithAStableCode(
        ?int $installed,
        CheckStatus $status,
        HealthCode $code,
    ): void {
        $check = new SchemaReadinessCheck(
            new HealthMigrationRepository($installed),
            new SchemaCompatibilityChecker(),
            4,
        );

        $result = $check->check();

        self::assertSame('database_schema', $result->identifier);
        self::assertSame($status, $result->status);
        self::assertSame($code, $result->code);
    }

    /** @return iterable<string, array{?int, CheckStatus, HealthCode}> */
    public static function schemaVersions(): iterable
    {
        yield 'compatible' => [4, CheckStatus::UP, HealthCode::OK];
        yield 'not installed' => [null, CheckStatus::DOWN, HealthCode::SCHEMA_MIGRATION_REQUIRED];
        yield 'older schema' => [3, CheckStatus::DOWN, HealthCode::SCHEMA_MIGRATION_REQUIRED];
        yield 'newer schema' => [5, CheckStatus::DOWN, HealthCode::APPLICATION_SCHEMA_INCOMPATIBLE];
    }

    public function testDatabaseProbeIsReadOnlyAndHealthyWhenSelectOneSucceeds(): void
    {
        $wpdb = new HealthWpdb(1);
        $check = new WpdbConnectionReadinessCheck(new WpdbAdapter($wpdb));

        $result = $check->check();

        self::assertSame(CheckStatus::UP, $result->status);
        self::assertSame(HealthCode::OK, $result->code);
        self::assertSame(['SELECT 1'], $wpdb->queries);
    }

    public function testDatabaseErrorsAreReducedToAStableNonSensitiveCode(): void
    {
        $wpdb = new HealthWpdb(null);
        $wpdb->last_error = 'password=secret at C:\\private\\database.php';
        $wpdb->last_errno = 1045;
        $check = new WpdbConnectionReadinessCheck(new WpdbAdapter($wpdb));

        $result = $check->check();

        self::assertSame(CheckStatus::DOWN, $result->status);
        self::assertSame(HealthCode::DATABASE_UNAVAILABLE, $result->code);
        self::assertStringNotContainsString('secret', $result->code->value);
        self::assertStringNotContainsString('private', $result->code->value);
    }
}

final class HealthMigrationRepository implements MigrationRepository
{
    public function __construct(private readonly ?int $schemaVersion)
    {
    }

    public function initialize(): void
    {
    }

    public function currentSchemaVersion(): ?int
    {
        return $this->schemaVersion;
    }

    public function find(string $key): ?MigrationRecord
    {
        return null;
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

final class HealthWpdb
{
    public string $prefix = 'wp_';
    public string $last_error = '';
    public int $last_errno = 0;

    /** @var list<string> */
    public array $queries = [];

    public function __construct(private readonly mixed $result)
    {
    }

    public function get_var(string $sql): mixed
    {
        $this->queries[] = $sql;

        return $this->result;
    }
}
