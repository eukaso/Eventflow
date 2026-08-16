<?php

namespace EventFlow\Infrastructure\Persistence\WordPress;

use EventFlow\Application\Migration\MigrationLock;

final class WpdbMigrationLock implements MigrationLock
{
    private bool $held = false;

    public function __construct(
        private WpdbAdapter $database,
        private string $name = 'eventflow_schema_migration',
        private int $timeoutSeconds = 0,
    ) {
    }

    public function acquire(): bool
    {
        $result = $this->database->fetchValue(
            'SELECT GET_LOCK(%s, %d)',
            [$this->name, $this->timeoutSeconds],
        );

        return $this->held = ((string) $result === '1');
    }

    public function release(): void
    {
        if (!$this->held) {
            return;
        }

        $this->database->fetchValue('SELECT RELEASE_LOCK(%s)', [$this->name]);
        $this->held = false;
    }
}
