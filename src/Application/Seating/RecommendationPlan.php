<?php

namespace EventFlow\Application\Seating;

final readonly class RecommendationPlan
{
    public const ALGORITHM_VERSION = 'greedy-groups-v1';

    /** @param list<RecommendedPlacement> $placements @param list<string> $warnings */
    public function __construct(
        public string $inputFingerprint,
        public string $algorithmVersion,
        public string $seed,
        public array $placements,
        public array $warnings = [],
    ) {
        if (!preg_match('/^[a-f0-9]{64}$/', $inputFingerprint) || trim($seed) === '') throw new SeatingException('recommendation_plan_invalid');
    }
}
