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
            new MigrationDefinition(
                key: '0003_idempotency_return_once',
                version: 'v0.8.0',
                fromSchemaVersion: 2,
                toSchemaVersion: 3,
                description: 'Persist only the sensitivity marker required for return-once replay semantics.',
                statements: $this->loader->load(
                    $this->databaseDirectory . '/migrations/0003-idempotency-return-once.sql',
                ),
            ),
            new MigrationDefinition(
                key: '0004_audit_integrity',
                version: 'v0.8.0',
                fromSchemaVersion: 3,
                toSchemaVersion: 4,
                description: 'Add versioned tamper-evident audit chains and locked per-scope heads.',
                statements: $this->loader->load(
                    $this->databaseDirectory . '/migrations/0004-audit-integrity.sql',
                ),
            ),
            new MigrationDefinition(
                key: '0005_export_resources',
                version: 'v0.9.0',
                fromSchemaVersion: 4,
                toSchemaVersion: 5,
                description: 'Add durable protected Event Export resources.',
                statements: $this->loader->load(
                    $this->databaseDirectory . '/migrations/0005-export-resources.sql',
                ),
            ),
            new MigrationDefinition(
                key: '0006_privacy_retention',
                version: 'v0.9.0',
                fromSchemaVersion: 5,
                toSchemaVersion: 6,
                description: 'Add restart-safe Privacy Actions, durable tombstones, and retention holds.',
                statements: $this->loader->load(
                    $this->databaseDirectory . '/migrations/0006-privacy-retention.sql',
                ),
            ),
            new MigrationDefinition(
                key: '0007_event_revision',
                version: 'v1.1.0-dev',
                fromSchemaVersion: 6,
                toSchemaVersion: 7,
                description: 'Add collision-free optimistic concurrency for Event updates.',
                statements: $this->loader->load(
                    $this->databaseDirectory . '/migrations/0007-event-revision.sql',
                ),
            ),
            new MigrationDefinition(
                key: '0008_venue_configuration_revisions',
                version: 'v1.1.0-dev',
                fromSchemaVersion: 7,
                toSchemaVersion: 8,
                description: 'Add collision-free optimistic concurrency for Venue and Event configuration updates.',
                statements: $this->loader->load(
                    $this->databaseDirectory . '/migrations/0008-venue-configuration-revisions.sql',
                ),
            ),
            new MigrationDefinition(
                key: '0009_invitation_revision',
                version: 'v1.1.0-dev',
                fromSchemaVersion: 8,
                toSchemaVersion: 9,
                description: 'Add collision-free optimistic concurrency for Invitation updates and lifecycle changes.',
                statements: $this->loader->load(
                    $this->databaseDirectory . '/migrations/0009-invitation-revision.sql',
                ),
            ),
        ];
    }
}
