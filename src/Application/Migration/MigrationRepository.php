<?php

namespace EventFlow\Application\Migration;

interface MigrationRepository
{
    /** Create the platform-scoped ledger when an explicit installer invokes the runner. */
    public function initialize(): void;

    public function currentSchemaVersion(): ?int;

    public function find(string $key): ?MigrationRecord;

    public function markRunning(MigrationDefinition $migration, string $executionSource): void;

    public function markCompleted(MigrationDefinition $migration, int $durationMilliseconds): void;

    public function markFailed(
        MigrationDefinition $migration,
        int $durationMilliseconds,
        string $errorCode,
    ): void;
}
