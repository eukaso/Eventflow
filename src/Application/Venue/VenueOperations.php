<?php

namespace EventFlow\Application\Venue;

use EventFlow\Application\Authorization\PrincipalContext;
use EventFlow\Application\Idempotency\IdempotencyOutcome;

interface VenueOperations
{
    public function list(PrincipalContext $principal, int $limit = 50, ?int $afterVenueId = null): VenuePage;
    public function read(PrincipalContext $principal, int $venueId): VenueRecord;
    public function create(PrincipalContext $principal, VenueAttributes $attributes, string $idempotencyKey): IdempotencyOutcome;
    public function update(PrincipalContext $principal, int $venueId, VenuePatch $patch, string $idempotencyKey): IdempotencyOutcome;
}
