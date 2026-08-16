<?php

namespace EventFlow\Infrastructure\Persistence\WordPress;

use EventFlow\Application\Migration\MigrationLock;

final class WpdbMigrationLock implements MigrationLock
{
    private bool $held = false;

    public function __construct(
        private object $wpdb,
        private string $name = 'eventflow_schema_migration',
        private int $timeoutSeconds = 0,
    ) {
    }

    public function acquire(): bool
    {
        $result = $this->wpdb->get_var(
            $this->wpdb->prepare('SELECT GET_LOCK(%s, %d)', $this->name, $this->timeoutSeconds),
        );

        return $this->held = ((string) $result === '1');
    }

    public function release(): void
    {
        if (!$this->held) {
            return;
        }

        $this->wpdb->get_var($this->wpdb->prepare('SELECT RELEASE_LOCK(%s)', $this->name));
        $this->held = false;
    }
}
