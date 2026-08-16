<?php

namespace EventFlow\Infrastructure\Persistence\WordPress;

use EventFlow\Infrastructure\Persistence\PersistenceException;

final class WpdbAdapter
{
    private string $prefix;

    public function __construct(private object $wpdb)
    {
        if (!isset($wpdb->prefix) || !is_string($wpdb->prefix) || !preg_match('/^[A-Za-z0-9_]+$/', $wpdb->prefix)) {
            throw new PersistenceException('invalid_wordpress_database_adapter');
        }

        $this->prefix = $wpdb->prefix;
    }

    public function tablePrefix(): string
    {
        return $this->prefix;
    }

    /** @param list<float|int|string|null> $parameters */
    public function prepare(string $sql, array $parameters = []): string
    {
        if ($parameters === []) {
            return $sql;
        }

        $prepared = $this->wpdb->prepare($sql, ...$parameters);

        if (!is_string($prepared) || $prepared === '') {
            throw new PersistenceException('database_prepare_failed');
        }

        return $prepared;
    }

    /** @param list<float|int|string|null> $parameters */
    public function fetchValue(string $sql, array $parameters = []): mixed
    {
        $value = $this->wpdb->get_var($this->prepare($sql, $parameters));
        $this->throwOnDatabaseError();

        return $value;
    }

    /**
     * @param list<float|int|string|null> $parameters
     * @return array<string, mixed>|null
     */
    public function fetchRow(string $sql, array $parameters = []): ?array
    {
        $row = $this->wpdb->get_row($this->prepare($sql, $parameters), 'ARRAY_A');
        $this->throwOnDatabaseError();

        if ($row === null) {
            return null;
        }

        if (!is_array($row)) {
            throw new PersistenceException('database_result_invalid');
        }

        return $row;
    }

    /**
     * @param list<float|int|string|null> $parameters
     * @return list<array<string, mixed>>
     */
    public function fetchAll(string $sql, array $parameters = []): array
    {
        $rows = $this->wpdb->get_results($this->prepare($sql, $parameters), 'ARRAY_A');
        $this->throwOnDatabaseError();

        if (!is_array($rows)) {
            throw new PersistenceException('database_result_invalid');
        }

        foreach ($rows as $row) {
            if (!is_array($row)) {
                throw new PersistenceException('database_result_invalid');
            }
        }

        return array_values($rows);
    }

    /** @param list<float|int|string|null> $parameters */
    public function execute(string $sql, array $parameters = []): int
    {
        $affected = $this->wpdb->query($this->prepare($sql, $parameters));

        if ($affected === false) {
            throw $this->databaseException();
        }

        return (int) $affected;
    }

    public function lastInsertId(): int
    {
        $insertId = $this->wpdb->insert_id ?? null;

        if (!is_int($insertId) && !is_numeric($insertId)) {
            throw new PersistenceException('database_insert_id_unavailable');
        }

        return (int) $insertId;
    }

    public function escapeLike(string $value): string
    {
        return (string) $this->wpdb->esc_like($value);
    }

    private function throwOnDatabaseError(): void
    {
        $error = $this->wpdb->last_error ?? '';

        if (is_string($error) && $error !== '') {
            throw $this->databaseException();
        }
    }

    private function databaseException(): PersistenceException
    {
        $errorNumber = (int) ($this->wpdb->last_errno ?? 0);

        return new PersistenceException(match ($errorNumber) {
            1213 => 'database_deadlock',
            1205 => 'database_lock_timeout',
            1062 => 'database_unique_conflict',
            default => 'database_query_failed',
        });
    }
}
