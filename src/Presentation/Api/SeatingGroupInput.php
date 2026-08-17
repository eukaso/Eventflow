<?php

namespace EventFlow\Presentation\Api;

use EventFlow\Application\Persistence\EventScope;
use EventFlow\Application\Seating\ConstraintLevel;

final readonly class SeatingGroupInput
{
    /** @param list<int> $attendeeIds */
    public function __construct(
        public EventScope $scope,
        public string $name,
        public string $category,
        public ConstraintLevel $constraint,
        public int $priority,
        public array $attendeeIds,
    ) {
    }
}
