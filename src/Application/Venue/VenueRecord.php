<?php

namespace EventFlow\Application\Venue;

use InvalidArgumentException;

final readonly class VenueRecord
{
    public function __construct(public int $venueId, public VenueAttributes $attributes, public int $revision)
    {
        if ($venueId < 1 || $revision < 1) throw new InvalidArgumentException('venue_record_invalid');
    }
}
