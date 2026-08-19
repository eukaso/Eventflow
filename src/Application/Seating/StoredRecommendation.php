<?php

namespace EventFlow\Application\Seating;

use DateTimeImmutable;
use EventFlow\Application\Persistence\EventScope;

final readonly class StoredRecommendation
{
    public function __construct(
        public int $recommendationId,
        public EventScope $eventScope,
        public RecommendationStatus $status,
        public RecommendationPlan $plan,
        public DateTimeImmutable $createdAt,
        public ?DateTimeImmutable $appliedAt = null,
    ) {
        if ($recommendationId < 1 || ($status === RecommendationStatus::APPLIED) !== ($appliedAt !== null)) {
            throw new SeatingException('stored_recommendation_invalid');
        }
    }
}
