<?php

namespace EventFlow\Application\Seating;

final readonly class SeatingSeatReplacement
{
    public function __construct(
        public string $label,
        public bool $accessible,
        public int $sortOrder,
        public int $expectedRevision,
    ) {
        if (trim($label) === '' || strlen(trim($label)) > 64 || $sortOrder < 0 || $sortOrder > 65535 || $expectedRevision < 1) {
            throw new SeatingException('seating_seat_configuration_invalid');
        }
    }
}
