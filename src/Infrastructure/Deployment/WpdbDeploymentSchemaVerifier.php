<?php

namespace EventFlow\Infrastructure\Deployment;

use EventFlow\Application\Deployment\DeploymentSchemaVerificationResult;
use EventFlow\Application\Migration\MigrationDefinition;
use EventFlow\Application\Migration\MigrationRepository;
use EventFlow\Infrastructure\Persistence\TableName;
use EventFlow\Infrastructure\Persistence\WordPress\WpdbAdapter;
use EventFlow\Infrastructure\Persistence\WordPress\WpdbTableNames;
use RuntimeException;

final readonly class WpdbDeploymentSchemaVerifier
{
    public function __construct(
        private WpdbAdapter $database,
        private WpdbTableNames $tables,
        private MigrationRepository $migrations,
    ) {
    }

    public function assertFreshInstall(): void
    {
        foreach (TableName::cases() as $table) {
            if ($this->table($table) !== null) {
                throw new RuntimeException('fresh_install_eventflow_tables_present');
            }
        }
    }

    /** @param list<MigrationDefinition> $definitions */
    public function verify(array $definitions, int $expectedSchemaVersion): DeploymentSchemaVerificationResult
    {
        if ($expectedSchemaVersion < 1 || $this->migrations->currentSchemaVersion() !== $expectedSchemaVersion) {
            throw new RuntimeException('deployment_schema_version_mismatch');
        }
        $migrationCount = 0;
        foreach ($definitions as $definition) {
            $record = $this->migrations->find($definition->key);
            if ($record === null
                || !$record->isCompleted()
                || $record->toSchemaVersion !== $definition->toSchemaVersion
                || !hash_equals($definition->checksum(), $record->checksum)
            ) {
                throw new RuntimeException('deployment_migration_ledger_mismatch');
            }
            $migrationCount++;
        }
        $tableCount = 0;
        foreach (TableName::cases() as $table) {
            $record = $this->table($table);
            if ($record === null
                || strtolower((string) ($record['ENGINE'] ?? '')) !== 'innodb'
                || !str_starts_with(strtolower((string) ($record['TABLE_COLLATION'] ?? '')), 'utf8mb4_')
            ) {
                throw new RuntimeException('deployment_table_contract_mismatch');
            }
            $tableCount++;
        }
        return new DeploymentSchemaVerificationResult($expectedSchemaVersion, $migrationCount, $tableCount);
    }

    /** @return array<string,mixed>|null */
    private function table(TableName $table): ?array
    {
        return $this->database->fetchRow(
            'SELECT ENGINE, TABLE_COLLATION FROM information_schema.TABLES ' .
            'WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = %s',
            [$this->tables->get($table)],
        );
    }
}
