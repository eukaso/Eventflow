<?php

namespace EventFlow\Presentation\Api;

use EventFlow\Application\Persistence\EventScope;

final readonly class SeatingGroupMoveInput
{
    /** @param list<\EventFlow\Application\Seating\SeatingGroupMoveMember> $members */
    public function __construct(
        public EventScope $scope,
        public int $groupId,
        public int $tableId,
        public int $expectedGroupRevision,
        public array $members,
        public bool $overrideRequiredGroups,
        public ?string $overrideReason,
    ) {}
}
