<?php

namespace EventFlow\Application\CheckIn;

use EventFlow\Application\Authorization\PrincipalContext;
use EventFlow\Application\Idempotency\IdempotencyOutcome;
use EventFlow\Application\Persistence\EventScope;

interface CheckInCommands
{
    public function checkIn(PrincipalContext $principal, EventScope $scope, int $attendeeId, ?int $stationId, CheckInMethod $method, string $key, ?string $notes = null): IdempotencyOutcome;

    /** @param list<int> $attendeeIds */
    public function bulk(PrincipalContext $principal, EventScope $scope, array $attendeeIds, ?int $stationId, CheckInMethod $method, string $key, ?string $notes = null): IdempotencyOutcome;

    public function reverse(PrincipalContext $principal, EventScope $scope, int $checkInId, string $reason, string $key): IdempotencyOutcome;
}
