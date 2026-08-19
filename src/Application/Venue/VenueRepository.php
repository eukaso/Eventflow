<?php

namespace EventFlow\Application\Venue;

use DateTimeImmutable;

interface VenueRepository
{
    public function list(int $limit, ?int $afterVenueId): VenuePage;
    public function find(int $venueId): ?VenueRecord;
    public function lock(int $venueId): ?VenueRecord;
    public function create(VenueAttributes $attributes, int $actorUserId, DateTimeImmutable $now): VenueRecord;
    public function update(VenueRecord $current, VenueAttributes $replacement, int $actorUserId, DateTimeImmutable $now): VenueRecord;
}
