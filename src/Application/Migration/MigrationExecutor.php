<?php

namespace EventFlow\Application\Migration;

interface MigrationExecutor
{
    public function execute(MigrationDefinition $migration): void;
}
