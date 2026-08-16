<?php

namespace EventFlow\Application\Seating;

final readonly class ConfiguredTable
{
    /** @param list<SeatingSeat> $seats */
    public function __construct(public SeatingTable $table, public array $seats) {}
}
