<?php

namespace EventFlow\Infrastructure\Persistence\WordPress;

use EventFlow\Application\Migration\MigrationRecord;
use EventFlow\Application\Migration\MigrationRepository;
use RuntimeException;

final class WpdbSchemaMetadataRepository implements MigrationRepository
{
    private string $table;

    public function __construct(private object $wpdb)
    {
        if (!isset($wpdb->prefix) || !is_string($wpdb->prefix)) {
            throw new RuntimeException('invalid_wordpress_database_adapter');
        }

        $this->table = $wpdb->prefix . 'eventflow_schema_migrations';
    }

    public function initialize(): void
    {
        if ($this->tableExists()) {
            return;
        }

        $sql = "CREATE TABLE IF NOT EXISTS {$this->table} (
            migration_id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            migration_key VARCHAR(100) NOT NULL,
            migration_version VARCHAR(32) NOT NULL,
            migration_type VARCHAR(32) NOT NULL DEFAULT 'schema',
            migration_status VARCHAR(32) NOT NULL DEFAULT 'pending',
            checksum CHAR(64) NOT NULL,
            description VARCHAR(500) NOT NULL,
            started_at DATETIME NULL,
            completed_at DATETIME NULL,
            failed_at DATETIME NULL,
            duration_ms BIGINT UNSIGNED NULL,
            executed_by_user_id BIGINT UNSIGNED NULL,
            execution_source VARCHAR(32) NOT NULL DEFAULT 'system',
            from_schema_version VARCHAR(32) NULL,
            to_schema_version VARCHAR(32) NULL,
            records_examined BIGINT UNSIGNED NULL,
            records_changed BIGINT UNSIGNED NULL,
            records_failed BIGINT UNSIGNED NULL,
            validation_status VARCHAR(32) NULL,
            rollback_available TINYINT(1) NOT NULL DEFAULT 0,
            rollback_reference VARCHAR(190) NULL,
            error_code VARCHAR(190) NULL,
            error_message TEXT NULL,
            created_at DATETIME NOT NULL,
            PRIMARY KEY (migration_id),
            UNIQUE KEY uq_schema_migration_key (migration_key),
            KEY idx_migration_status (migration_status, started_at),
            KEY idx_migration_version (migration_version),
            KEY idx_migration_schema_version (to_schema_version),
            KEY idx_migration_type (migration_type, migration_status),
            KEY idx_migration_validation (validation_status, completed_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";

        if ($this->wpdb->query($sql) === false) {
            throw new RuntimeException('migration_ledger_initialization_failed');
        }
    }

    public function currentSchemaVersion(): ?int
    {
        if (!$this->tableExists()) {
            return null;
        }

        $value = $this->wpdb->get_var(
            "SELECT MAX(CAST(to_schema_version AS UNSIGNED)) FROM {$this->table} " .
            "WHERE migration_status = 'completed' AND validation_status = 'passed'",
        );

        return $value === null ? null : (int) $value;
    }

    public function find(string $key): ?MigrationRecord
    {
        if (!$this->tableExists()) {
            return null;
        }

        $row = $this->wpdb->get_row(
            $this->wpdb->prepare(
                "SELECT migration_key, migration_status, checksum, to_schema_version " .
                "FROM {$this->table} WHERE migration_key = %s",
                $key,
            ),
            ARRAY_A,
        );

        if (!is_array($row)) {
            return null;
        }

        return new MigrationRecord(
            (string) $row['migration_key'],
            (string) $row['migration_status'],
            (string) $row['checksum'],
            (int) $row['to_schema_version'],
        );
    }

    public function markRunning(
        \EventFlow\Application\Migration\MigrationDefinition $migration,
        string $executionSource,
    ): void {
        $now = gmdate('Y-m-d H:i:s');
        $result = $this->wpdb->query(
            $this->wpdb->prepare(
                "INSERT INTO {$this->table} " .
                '(migration_key, migration_version, migration_type, migration_status, checksum, description, ' .
                'started_at, execution_source, from_schema_version, to_schema_version, validation_status, ' .
                'rollback_available, created_at) VALUES (%s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, 0, %s)',
                $migration->key,
                $migration->version,
                'schema',
                'running',
                $migration->checksum(),
                $migration->description,
                $now,
                $executionSource,
                (string) $migration->fromSchemaVersion,
                (string) $migration->toSchemaVersion,
                'pending',
                $now,
            ),
        );

        if ($result === false) {
            throw new RuntimeException('migration_ledger_write_failed');
        }
    }

    public function markCompleted(
        \EventFlow\Application\Migration\MigrationDefinition $migration,
        int $durationMilliseconds,
    ): void {
        $this->updateStatus(
            $migration->key,
            "migration_status = 'completed', validation_status = 'passed', completed_at = %s, duration_ms = %d",
            [gmdate('Y-m-d H:i:s'), $durationMilliseconds],
        );
    }

    public function markFailed(
        \EventFlow\Application\Migration\MigrationDefinition $migration,
        int $durationMilliseconds,
        string $errorCode,
    ): void {
        $this->updateStatus(
            $migration->key,
            "migration_status = 'failed', validation_status = 'failed', failed_at = %s, duration_ms = %d, error_code = %s",
            [gmdate('Y-m-d H:i:s'), $durationMilliseconds, $errorCode],
        );
    }

    private function tableExists(): bool
    {
        $found = $this->wpdb->get_var(
            $this->wpdb->prepare('SHOW TABLES LIKE %s', $this->wpdb->esc_like($this->table)),
        );

        return $found === $this->table;
    }

    /** @param list<int|string> $values */
    private function updateStatus(string $key, string $setClause, array $values): void
    {
        $values[] = $key;
        $sql = $this->wpdb->prepare(
            "UPDATE {$this->table} SET {$setClause} WHERE migration_key = %s AND migration_status = 'running'",
            ...$values,
        );

        if ($this->wpdb->query($sql) !== 1) {
            throw new RuntimeException('migration_ledger_write_failed');
        }
    }
}
