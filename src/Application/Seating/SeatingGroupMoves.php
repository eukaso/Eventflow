<?php

namespace EventFlow\Application\Seating;

use EventFlow\Application\Authorization\PrincipalContext;
use EventFlow\Application\Idempotency\IdempotencyOutcome;
use EventFlow\Application\Persistence\EventScope;

interface SeatingGroupMoves
{
    /** @param list<SeatingGroupMoveMember> $members */
    public function moveGroup(
        PrincipalContext $principal,
        EventScope $scope,
        int $groupId,
        int $tableId,
        int $expectedGroupRevision,
        array $members,
        bool $overrideRequiredGroups,
        ?string $overrideReason,
        string $idempotencyKey,
    ): IdempotencyOutcome;
}
