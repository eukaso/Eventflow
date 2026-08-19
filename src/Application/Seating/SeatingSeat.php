<?php

namespace EventFlow\Application\Seating;

final readonly class SeatingSeat
{
    public function __construct(
        public int $seatId,
        public int $tableId,
        public string $label,
        public bool $accessible = false,
        public int $sortOrder = 100,
        public int $revision = 1,
    ) {
        if ($seatId < 1 || $tableId < 1 || trim($label) === '' || $sortOrder < 0 || $revision < 1) throw new SeatingException('seating_seat_invalid');
    }
}
