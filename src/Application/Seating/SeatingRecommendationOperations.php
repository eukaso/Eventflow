<?php

namespace EventFlow\Application\Seating;

use EventFlow\Application\Authorization\PrincipalContext;
use EventFlow\Application\Idempotency\IdempotencyOutcome;
use EventFlow\Application\Persistence\EventScope;

interface SeatingRecommendationOperations
{
    public function generate(PrincipalContext $principal, EventScope $scope, string $seed, string $idempotencyKey): IdempotencyOutcome;
    public function get(PrincipalContext $principal, EventScope $scope, int $recommendationId): StoredRecommendation;
    public function apply(PrincipalContext $principal, EventScope $scope, int $recommendationId, string $idempotencyKey): IdempotencyOutcome;
}
