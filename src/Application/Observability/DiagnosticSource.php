<?php

namespace EventFlow\Application\Observability;

interface DiagnosticSource
{
    public function identifier(): string;

    /** @return array<string, mixed> */
    public function snapshot(): array;
}
