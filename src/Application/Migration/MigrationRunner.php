<?php

namespace EventFlow\Application\Migration;

use Throwable;

final readonly class MigrationRunner
{
    public function __construct(
        private MigrationRepository $repository,
        private MigrationLock $lock,
        private MigrationExecutor $executor,
        private MigrationPreflight $preflight,
    ) {
    }

    /**
     * This entry point is for explicit CLI/upgrade infrastructure only.
     * Normal application bootstrap must never invoke it.
     *
     * @param list<MigrationDefinition> $migrations
     * @return list<string> Applied migration keys
     */
    public function run(array $migrations, string $executionSource): array
    {
        if (!in_array($executionSource, ['plugin_upgrade', 'cli', 'admin_ui', 'deployment', 'system'], true)) {
            throw new MigrationException('invalid_migration_execution_source');
        }

        if (!$this->lock->acquire()) {
            throw new MigrationException('migration_lock_unavailable');
        }

        try {
            $this->repository->initialize();
            $pending = $this->preflight->pending($migrations, $this->repository);
            $applied = [];

            foreach ($pending as $migration) {
                $startedAt = hrtime(true);
                $this->repository->markRunning($migration, $executionSource);

                try {
                    $this->executor->execute($migration);
                    $duration = $this->elapsedMilliseconds($startedAt);
                    $this->repository->markCompleted($migration, $duration);
                    $applied[] = $migration->key;
                } catch (Throwable $throwable) {
                    $this->repository->markFailed(
                        $migration,
                        $this->elapsedMilliseconds($startedAt),
                        'migration_execution_failed',
                    );

                    throw new MigrationException('migration_execution_failed', 0, $throwable);
                }
            }

            return $applied;
        } finally {
            $this->lock->release();
        }
    }

    private function elapsedMilliseconds(int $startedAt): int
    {
        return max(0, (int) ((hrtime(true) - $startedAt) / 1_000_000));
    }
}
