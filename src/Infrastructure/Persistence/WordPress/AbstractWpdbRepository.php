<?php

namespace EventFlow\Infrastructure\Persistence\WordPress;

use EventFlow\Application\Persistence\EventScope;
use EventFlow\Application\Persistence\LockMode;
use EventFlow\Application\Persistence\PageRequest;
use EventFlow\Infrastructure\Persistence\TableName;

abstract class AbstractWpdbRepository
{
    public function __construct(
        protected WpdbAdapter $database,
        private WpdbTableNames $tableNames,
    ) {
    }

    final protected function table(TableName $table): string
    {
        return $this->tableNames->get($table);
    }

    final protected function eventId(EventScope $scope): int
    {
        return $scope->eventId;
    }

    final protected function pageClause(PageRequest $page): string
    {
        return $this->database->prepare('LIMIT %d OFFSET %d', [$page->limit, $page->offset]);
    }

    final protected function lockClause(LockMode $lockMode): string
    {
        return match ($lockMode) {
            LockMode::NONE => '',
            LockMode::FOR_UPDATE => ' FOR UPDATE',
        };
    }
}
