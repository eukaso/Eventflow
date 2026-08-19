<?php

namespace EventFlow\Presentation\Api;

use EventFlow\Application\Seating\SeatingRecommendationOperations;

final readonly class SeatingRecommendationController
{
    public function __construct(
        private SeatingRecommendationOperations $recommendations,
        private AuthenticatedRequestContextFactory $contexts,
        private SeatingRecommendationRequestMapper $requests,
        private SeatingRecommendationPresenter $presenter,
    ) {
    }

    public function generate(RestRequest $request): ApiResponse
    {
        $context = $this->contexts->create($request, MutationPreconditionPolicy::IDEMPOTENCY_KEY);
        [$scope, $seed] = $this->requests->generate($request);
        return $this->presenter->outcome($this->recommendations->generate($context->principal, $scope, $seed, $context->requiredIdempotencyKey()), $scope->eventId, $context->requestId);
    }

    public function get(RestRequest $request): ApiResponse
    {
        $context = $this->contexts->create($request, MutationPreconditionPolicy::NONE);
        $scope = $this->requests->scope($request);
        return $this->presenter->resource($this->recommendations->get($context->principal, $scope, $this->requests->recommendationId($request)), $context->requestId);
    }

    public function apply(RestRequest $request): ApiResponse
    {
        $context = $this->contexts->create($request, MutationPreconditionPolicy::IDEMPOTENCY_KEY);
        $this->requests->requireEmptyBody($request);
        $scope = $this->requests->scope($request);
        return $this->presenter->outcome($this->recommendations->apply($context->principal, $scope, $this->requests->recommendationId($request), $context->requiredIdempotencyKey()), $scope->eventId, $context->requestId);
    }
}
