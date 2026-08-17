<?php

namespace EventFlow\Application\Seating;

use EventFlow\Application\Authorization\PrincipalContext;
use EventFlow\Application\Idempotency\IdempotencyOutcome;
use EventFlow\Application\Persistence\EventScope;

interface SeatingPreparation
{
    /** @param list<array{label:string, accessible?:bool}> $seats */
    public function createTable(PrincipalContext $principal, EventScope $scope, string $name, int $capacity, array $seats, string $idempotencyKey): IdempotencyOutcome;

    /** @param list<int> $attendeeIds */
    public function createGroup(PrincipalContext $principal, EventScope $scope, string $name, string $category, ConstraintLevel $constraint, int $priority, array $attendeeIds, string $idempotencyKey): IdempotencyOutcome;

    public function readiness(PrincipalContext $principal, EventScope $scope): SeatingReadiness;
}
