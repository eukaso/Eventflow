<?php

namespace EventFlow\Application\Migration;

final class MigrationPreflight
{
    /**
     * @param list<MigrationDefinition> $migrations
     * @return list<MigrationDefinition>
     */
    public function pending(array $migrations, MigrationRepository $repository): array
    {
        usort(
            $migrations,
            static fn (MigrationDefinition $left, MigrationDefinition $right): int =>
                $left->toSchemaVersion <=> $right->toSchemaVersion,
        );

        $keys = [];
        $versions = [];
        $current = $repository->currentSchemaVersion() ?? 0;
        $pending = [];

        foreach ($migrations as $migration) {
            if (isset($keys[$migration->key])) {
                throw new MigrationException('duplicate_migration_key');
            }

            if (isset($versions[$migration->toSchemaVersion])) {
                throw new MigrationException('duplicate_schema_version');
            }

            $keys[$migration->key] = true;
            $versions[$migration->toSchemaVersion] = true;
            $record = $repository->find($migration->key);

            if ($record !== null) {
                if (!$record->isCompleted()) {
                    throw new MigrationException('migration_requires_forward_repair');
                }

                if (!hash_equals($record->checksum, $migration->checksum())) {
                    throw new MigrationException('completed_migration_checksum_mismatch');
                }

                $current = max($current, $record->toSchemaVersion);
                continue;
            }

            if ($migration->fromSchemaVersion !== $current) {
                throw new MigrationException('non_contiguous_migration_plan');
            }

            $pending[] = $migration;
            $current = $migration->toSchemaVersion;
        }

        return $pending;
    }
}
