<?php

namespace EventFlow\Application\Venue;

final readonly class VenuePage
{
    /** @param list<VenueRecord> $venues */
    public function __construct(public array $venues, public ?int $nextAfterVenueId) {}
}
