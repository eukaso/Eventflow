<?php

namespace EventFlow\Presentation\Api;

use EventFlow\Application\Persistence\EventScope;

final readonly class CheckInReversalInput
{
    public function __construct(public EventScope $scope, public int $checkInId, public string $reason) {}
}
