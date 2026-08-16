<?php

namespace EventFlow\Application\Migration;

final readonly class MigrationRecord
{
    public function __construct(
        public string $key,
        public string $status,
        public string $checksum,
        public int $toSchemaVersion,
    ) {
    }

    public function isCompleted(): bool
    {
        return $this->status === 'completed';
    }
}
