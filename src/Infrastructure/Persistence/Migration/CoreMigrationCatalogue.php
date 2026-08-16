<?php

namespace EventFlow\Infrastructure\Persistence\Migration;

use EventFlow\Application\Migration\MigrationDefinition;

final readonly class CoreMigrationCatalogue
{
    public function __construct(
        private string $databaseDirectory,
        private SqlMigrationLoader $loader,
    ) {
    }

    /** @return list<MigrationDefinition> */
    public function definitions(): array
    {
        return [
            new MigrationDefinition(
                key: '0001_sprint_3_baseline',
                version: 'v0.4.0',
                fromSchemaVersion: 0,
                toSchemaVersion: 1,
                description: 'Install the frozen Sprint 3 database baseline.',
                statements: $this->loader->load(
                    $this->databaseDirectory . '/eventflow-schema-baseline-v1.0.sql',
                    true,
                ),
            ),
            new MigrationDefinition(
                key: '0002_foundation_security_operations',
                version: 'v0.8.0',
                fromSchemaVersion: 1,
                toSchemaVersion: 2,
                description: 'Add guest security, RSVP revision, idempotency, job, and worker-lease state.',
                statements: $this->loader->load(
                    $this->databaseDirectory . '/migrations/0002-foundation-security-and-operations.sql',
                ),
            ),
        ];
    }
}
