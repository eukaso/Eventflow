<?php

namespace EventFlow\Application\Seating;

final readonly class RecommendedPlacement
{
    public function __construct(public int $attendeeId, public int $tableId, public ?int $seatId, public string $reason) {}
}
