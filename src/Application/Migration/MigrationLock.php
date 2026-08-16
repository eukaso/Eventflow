<?php

namespace EventFlow\Application\Migration;

interface MigrationLock
{
    public function acquire(): bool;

    public function release(): void;
}
