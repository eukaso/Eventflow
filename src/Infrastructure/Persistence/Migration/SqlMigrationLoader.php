<?php

namespace EventFlow\Infrastructure\Persistence\Migration;

use EventFlow\Application\Migration\MigrationException;

final readonly class SqlMigrationLoader
{
    public function __construct(private string $databasePrefix)
    {
        if (!preg_match('/^[A-Za-z0-9_]+$/', $databasePrefix)) {
            throw new MigrationException('invalid_database_prefix');
        }
    }

    /** @return non-empty-list<string> */
    public function load(string $path, bool $initialBaseline = false): array
    {
        $sql = file_get_contents($path);

        if ($sql === false || trim($sql) === '') {
            throw new MigrationException('migration_sql_unreadable');
        }

        if ($initialBaseline) {
            $sql = str_replace(
                'CREATE TABLE {prefix}eventflow_schema_migrations',
                'CREATE TABLE IF NOT EXISTS {prefix}eventflow_schema_migrations',
                $sql,
            );
        }

        $sql = str_replace('{prefix}', $this->databasePrefix, $sql);
        $parts = preg_split('/;\s*(?:\r?\n|$)/', $sql);

        if ($parts === false) {
            throw new MigrationException('migration_sql_parse_failed');
        }

        $statements = array_values(array_filter(
            array_map('trim', $parts),
            static fn (string $statement): bool => $statement !== '',
        ));

        if ($statements === []) {
            throw new MigrationException('migration_requires_sql');
        }

        return $statements;
    }
}
