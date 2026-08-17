<?php

namespace EventFlow\Presentation\Api;

use EventFlow\Application\CheckIn\CheckInMethod;
use EventFlow\Application\Persistence\EventScope;

final readonly class CheckInInput
{
    /** @param list<int> $attendeeIds */
    public function __construct(
        public EventScope $scope,
        public array $attendeeIds,
        public ?int $stationId,
        public CheckInMethod $method,
        public ?string $notes,
    ) {}
}
