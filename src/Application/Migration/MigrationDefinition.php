<?php

namespace EventFlow\Application\Migration;

final readonly class MigrationDefinition
{
    /**
     * @param non-empty-list<string> $statements
     */
    public function __construct(
        public string $key,
        public string $version,
        public int $fromSchemaVersion,
        public int $toSchemaVersion,
        public string $description,
        public array $statements,
    ) {
        if ($key === '' || strlen($key) > 100) {
            throw new MigrationException('invalid_migration_key');
        }

        if ($version === '' || strlen($version) > 32) {
            throw new MigrationException('invalid_migration_version');
        }

        if ($description === '' || strlen($description) > 500) {
            throw new MigrationException('invalid_migration_description');
        }

        if ($toSchemaVersion <= $fromSchemaVersion) {
            throw new MigrationException('migration_must_move_forward');
        }

        if ($statements === [] || in_array('', $statements, true)) {
            throw new MigrationException('migration_requires_sql');
        }
    }

    public function checksum(): string
    {
        $canonical = json_encode([
            'key' => $this->key,
            'version' => $this->version,
            'from' => $this->fromSchemaVersion,
            'to' => $this->toSchemaVersion,
            'description' => $this->description,
            'statements' => array_map(
                static fn (string $statement): string => trim(str_replace(["\r\n", "\r"], "\n", $statement)),
                $this->statements,
            ),
        ], JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES);

        return hash('sha256', $canonical);
    }
}
