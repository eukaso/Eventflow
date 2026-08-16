<?php

namespace EventFlow\Infrastructure\Persistence\WordPress;

use EventFlow\Infrastructure\Persistence\PersistenceException;
use EventFlow\Infrastructure\Persistence\TableName;

final readonly class WpdbTableNames
{
    public function __construct(private string $wordpressPrefix)
    {
        if (!preg_match('/^[A-Za-z0-9_]+$/', $wordpressPrefix)) {
            throw new PersistenceException('invalid_database_prefix');
        }
    }

    public function get(TableName $table): string
    {
        return $this->wordpressPrefix . 'eventflow_' . $table->value;
    }
}
