<?php

namespace EventFlow\Application\Seating;

use EventFlow\Application\Authorization\PrincipalContext;
use EventFlow\Application\Idempotency\IdempotencyOutcome;
use EventFlow\Application\Persistence\EventScope;

interface SeatingPlanningCommands
{
    public function recommend(PrincipalContext $principal, EventScope $scope, string $seed): RecommendationPlan;

    public function assign(
        PrincipalContext $principal,
        EventScope $scope,
        int $attendeeId,
        int $tableId,
        ?int $seatId,
        ?int $expectedAssignmentId,
        bool $overrideRequiredGroup,
        ?string $overrideReason,
        string $idempotencyKey,
    ): IdempotencyOutcome;
}
