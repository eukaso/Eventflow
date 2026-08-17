<?php

namespace EventFlow\Presentation\Api;

use EventFlow\Application\Persistence\EventScope;

final readonly class SeatingRecommendationInput
{
    public function __construct(public EventScope $scope, public string $seed)
    {
    }
}
