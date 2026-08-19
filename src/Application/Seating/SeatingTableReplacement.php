<?php

namespace EventFlow\Application\Seating;

final readonly class SeatingTableReplacement
{
    public function __construct(
        public string $name,
        public int $capacity,
        public int $sortOrder,
        public int $expectedRevision,
    ) {
        if (trim($name) === '' || strlen(trim($name)) > 190 || $capacity < 1 || $capacity > 65535 || $sortOrder < 0 || $expectedRevision < 1) {
            throw new SeatingException('seating_table_configuration_invalid');
        }
    }
}
