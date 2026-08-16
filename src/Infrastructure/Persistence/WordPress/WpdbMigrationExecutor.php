<?php

namespace EventFlow\Infrastructure\Persistence\WordPress;

use EventFlow\Application\Migration\MigrationDefinition;
use EventFlow\Application\Migration\MigrationExecutor;
use RuntimeException;

final readonly class WpdbMigrationExecutor implements MigrationExecutor
{
    public function __construct(private object $wpdb)
    {
    }

    public function execute(MigrationDefinition $migration): void
    {
        foreach ($migration->statements as $statement) {
            if ($this->wpdb->query($statement) === false) {
                throw new RuntimeException('migration_statement_failed');
            }
        }
    }
}
