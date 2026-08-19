<?php

namespace EventFlow\Presentation\Api;

use EventFlow\Application\Persistence\EventScope;

final readonly class SeatingRecommendationRequestMapper
{
    /** @return array{EventScope, string} */
    public function generate(RestRequest $request): array
    {
        $json = $request->json();
        if (array_keys($json) !== ['seed'] || !is_string($json['seed'])) throw new RequestInputException('validation_failed');
        return [$this->scope($request), trim($json['seed'])];
    }

    public function scope(RestRequest $request): EventScope { return new EventScope($this->routeId($request, 'event_id')); }
    public function recommendationId(RestRequest $request): int { return $this->routeId($request, 'recommendation_id'); }

    public function requireEmptyBody(RestRequest $request): void
    {
        if ($request->json() !== []) throw new RequestInputException('validation_failed');
    }

    private function routeId(RestRequest $request, string $name): int
    {
        $candidate = $request->route($name);
        if ($candidate === null || !ctype_digit($candidate)) throw new RequestInputException('resource_not_found');
        $id = filter_var($candidate, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        if ($id === false) throw new RequestInputException('resource_not_found');
        return $id;
    }
}
