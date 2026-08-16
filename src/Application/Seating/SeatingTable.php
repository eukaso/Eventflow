<?php

namespace EventFlow\Application\Seating;

final readonly class SeatingTable
{
    public function __construct(public int $tableId, public string $name, public int $capacity, public int $sortOrder = 100)
    {
        if ($tableId < 1 || trim($name) === '' || $capacity < 1) throw new SeatingException('seating_table_invalid');
    }
}
