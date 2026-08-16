<?php

namespace EventFlow\Tests\Unit\Infrastructure\Job;

use EventFlow\Application\Job\JobException;
use EventFlow\Application\Migration\MigrationDefinition;
use EventFlow\Application\Migration\MigrationRecord;
use EventFlow\Application\Migration\MigrationRepository;
use EventFlow\Bootstrap\SchemaCompatibilityChecker;
use EventFlow\Infrastructure\Job\MigrationWorkerSchemaGate;
use PHPUnit\Framework\TestCase;

final class MigrationWorkerSchemaGateTest extends TestCase
{
    public function testExactSchemaVersionAllowsWorker(): void
    {
        (new MigrationWorkerSchemaGate(
            new WorkerGateMigrationRepository(4),
            new SchemaCompatibilityChecker(),
            4,
        ))->assertCompatible();

        self::assertTrue(true);
    }

    public function testOlderOrNewerSchemaRefusesWorker(): void
    {
        foreach ([3, 5, null] as $installed) {
            try {
                (new MigrationWorkerSchemaGate(
                    new WorkerGateMigrationRepository($installed),
                    new SchemaCompatibilityChecker(),
                    4,
                ))->assertCompatible();
                self::fail('Expected worker schema refusal.');
            } catch (JobException $exception) {
                self::assertSame('job_worker_schema_incompatible', $exception->safeCode);
            }
        }
    }
}

final class WorkerGateMigrationRepository implements MigrationRepository
{
    public function __construct(private ?int $version)
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
