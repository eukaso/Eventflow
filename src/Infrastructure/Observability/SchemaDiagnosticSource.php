<?php

namespace EventFlow\Infrastructure\Observability;

use EventFlow\Application\Migration\MigrationRepository;
use EventFlow\Application\Observability\DiagnosticSource;

final readonly class SchemaDiagnosticSource implements DiagnosticSource
{
    public function __construct(private MigrationRepository $migrations, private int $expectedVersion)
    {
    }

    public function identifier(): string
    {
        return 'schema';
    }

    public function snapshot(): array
    {
        $current = $this->migrations->currentSchemaVersion();
        return [
            'current_version' => $current,
            'expected_version' => $this->expectedVersion,
            'compatible' => $current === $this->expectedVersion,
        ];
    }
}
