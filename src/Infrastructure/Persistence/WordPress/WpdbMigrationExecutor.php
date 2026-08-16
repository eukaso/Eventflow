<?php

namespace EventFlow\Infrastructure\Persistence\WordPress;

use EventFlow\Application\Migration\MigrationDefinition;
use EventFlow\Application\Migration\MigrationExecutor;
use RuntimeException;

final readonly class WpdbMigrationExecutor implements MigrationExecutor
{
    public function __construct(private WpdbAdapter $database)
    {
    }

    public function execute(MigrationDefinition $migration): void
    {
        foreach ($migration->statements as $statement) {
            try {
                $this->database->execute($statement);
            } catch (\Throwable $throwable) {
                throw new RuntimeException('migration_statement_failed', 0, $throwable);
            }
        }
    }
}
