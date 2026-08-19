<?php

namespace EventFlow\Application\Seating;

use DateTimeImmutable;
use EventFlow\Application\Persistence\EventScope;

interface SeatingRecommendationRepository
{
    public function create(EventScope $scope, RecommendationPlan $plan, int $actorUserId, DateTimeImmutable $now): StoredRecommendation;
    public function find(EventScope $scope, int $recommendationId): ?StoredRecommendation;
    public function lock(EventScope $scope, int $recommendationId): ?StoredRecommendation;
    public function markApplied(StoredRecommendation $recommendation, int $actorUserId, DateTimeImmutable $now): StoredRecommendation;
}
