<?php

namespace EventFlow\Application\Venue;

interface VenueAuthority
{
    public function canManageVenues(int $userId): bool;
}
