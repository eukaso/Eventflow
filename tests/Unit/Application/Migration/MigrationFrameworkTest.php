<?php

namespace EventFlow\Tests\Unit\Application\Migration;

use EventFlow\Application\Migration\MigrationDefinition;
use EventFlow\Application\Migration\MigrationException;
use EventFlow\Application\Migration\MigrationExecutor;
use EventFlow\Application\Migration\MigrationLock;
use EventFlow\Application\Migration\MigrationPreflight;
use EventFlow\Application\Migration\MigrationRecord;
use EventFlow\Application\Migration\MigrationRepository;
use EventFlow\Application\Migration\MigrationRunner;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class MigrationFrameworkTest extends TestCase
{
    public function testChecksumIsStableAcrossLineEndings(): void
    {
        $first = $this->migration("CREATE TABLE example (\r\n id INT\r\n)");
        $second = $this->migration("CREATE TABLE example (\n id INT\n)");

        self::assertSame($first->checksum(), $second->checksum());
        self::assertSame(64, strlen($first->checksum()));
    }

    public function testDefinitionRejectsBackwardMigration(): void
    {
        $this->expectException(MigrationException::class);
        $this->expectExceptionMessage('migration_must_move_forward');

        new MigrationDefinition('bad', '1.0.0', 2, 1, 'Backward migration', ['SELECT 1']);
    }

    public function testPreflightRejectsChangedCompletedMigration(): void
    {
        $migration = $this->migration('SELECT 1');
        $repository = new InMemoryMigrationRepository(1);
        $repository->records[$migration->key] = new MigrationRecord(
            $migration->key,
            'completed',
            str_repeat('0', 64),
            1,
        );

        $this->expectException(MigrationException::class);
        $this->expectExceptionMessage('completed_migration_checksum_mismatch');

        (new MigrationPreflight())->pending([$migration], $repository);
    }

    public function testRunnerAppliesContiguousMigrationsAndReleasesLock(): void
    {
        $repository = new InMemoryMigrationRepository();
        $lock = new InMemoryMigrationLock();
        $executor = new RecordingMigrationExecutor();
        $runner = new MigrationRunner($repository, $lock, $executor, new MigrationPreflight());
        $first = $this->migration('SELECT 1');
        $second = new MigrationDefinition('002_second', '0.8.0', 1, 2, 'Second', ['SELECT 2']);

        $applied = $runner->run([$second, $first], 'cli');

        self::assertSame(['001_initial', '002_second'], $applied);
        self::assertSame(['001_initial', '002_second'], $executor->executed);
        self::assertFalse($lock->held);
        self::assertTrue($repository->initialized);
        self::assertSame(2, $repository->currentSchemaVersion());
    }

    public function testFailureIsRecordedAndRequiresForwardRepair(): void
    {
        $repository = new InMemoryMigrationRepository();
        $lock = new InMemoryMigrationLock();
        $executor = new RecordingMigrationExecutor(true);
        $runner = new MigrationRunner($repository, $lock, $executor, new MigrationPreflight());
        $migration = $this->migration('INVALID SQL');

        try {
            $runner->run([$migration], 'deployment');
            self::fail('Expected migration failure.');
        } catch (MigrationException $exception) {
            self::assertSame('migration_execution_failed', $exception->getMessage());
        }

        self::assertSame('failed', $repository->records[$migration->key]->status);
        self::assertFalse($lock->held);

        $this->expectException(MigrationException::class);
        $this->expectExceptionMessage('migration_requires_forward_repair');
        (new MigrationPreflight())->pending([$migration], $repository);
    }

    private function migration(string $sql): MigrationDefinition
    {
        return new MigrationDefinition('001_initial', '0.8.0', 0, 1, 'Initial migration', [$sql]);
    }
}

final class InMemoryMigrationRepository implements MigrationRepository
{
    /** @var array<string, MigrationRecord> */
    public array $records = [];
    public bool $initialized = false;

    public function __construct(private ?int $version = null)
    {
    }

    public function initialize(): void
    {
        $this->initialized = true;
    }

    public function currentSchemaVersion(): ?int
    {
        return $this->version;
    }

    public function find(string $key): ?MigrationRecord
    {
        return $this->records[$key] ?? null;
    }

    public function markRunning(MigrationDefinition $migration, string $executionSource): void
    {
        $this->records[$migration->key] = new MigrationRecord(
            $migration->key,
            'running',
            $migration->checksum(),
            $migration->toSchemaVersion,
        );
    }

    public function markCompleted(MigrationDefinition $migration, int $durationMilliseconds): void
    {
        $this->records[$migration->key] = new MigrationRecord(
            $migration->key,
            'completed',
            $migration->checksum(),
            $migration->toSchemaVersion,
        );
        $this->version = $migration->toSchemaVersion;
    }

    public function markFailed(
        MigrationDefinition $migration,
        int $durationMilliseconds,
        string $errorCode,
    ): void {
        $this->records[$migration->key] = new MigrationRecord(
            $migration->key,
            'failed',
            $migration->checksum(),
            $migration->toSchemaVersion,
        );
    }
}

final class InMemoryMigrationLock implements MigrationLock
{
    public bool $held = false;

    public function acquire(): bool
    {
        return $this->held = true;
    }

    public function release(): void
    {
        $this->held = false;
    }
}

final class RecordingMigrationExecutor implements MigrationExecutor
{
    /** @var list<string> */
    public array $executed = [];

    public function __construct(private bool $fail = false)
    {
    }

    public function execute(MigrationDefinition $migration): void
    {
        $this->executed[] = $migration->key;

        if ($this->fail) {
            throw new RuntimeException('statement_failed');
        }
    }
}
