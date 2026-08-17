<?php

namespace EventFlow\Presentation\Api;

use EventFlow\Application\Persistence\EventScope;

final readonly class SeatingAssignmentInput
{
    public function __construct(
        public EventScope $scope,
        public int $attendeeId,
        public int $tableId,
        public ?int $seatId,
        public ?int $expectedAssignmentId,
        public bool $overrideRequiredGroup,
        public ?string $overrideReason,
    ) {
    }
}
