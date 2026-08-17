<?php

namespace EventFlow\Application\Observability;

interface LogSink
{
    /** @param array<string, mixed> $record */
    public function write(array $record): void;
}
