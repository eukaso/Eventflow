<?php

namespace EventFlow\Presentation\Api;

use EventFlow\Application\Persistence\EventScope;

final readonly class SeatingPlanningRequestMapper
{
    public function recommendation(RestRequest $request): SeatingRecommendationInput
    {
        $json = $this->only($request, ['seed']);
        return new SeatingRecommendationInput($this->scope($request), $this->requiredString($json['seed'] ?? null));
    }

    public function assignment(RestRequest $request): SeatingAssignmentInput
    {
        $json = $this->only($request, [
            'table_id', 'seat_id', 'expected_assignment_id', 'override_required_group', 'override_reason',
        ]);
        $override = $json['override_required_group'] ?? false;
        if (!is_bool($override)) throw new RequestInputException('validation_failed');
        $reason = $this->optionalString($json['override_reason'] ?? null);
        if (!$override && $reason !== null) throw new RequestInputException('validation_failed');
        return new SeatingAssignmentInput(
            $this->scope($request),
            $this->routeId($request, 'attendee_id'),
            $this->positiveInt($json['table_id'] ?? null),
            $this->optionalPositiveInt($json['seat_id'] ?? null),
            $this->optionalPositiveInt($json['expected_assignment_id'] ?? null),
            $override,
            $reason,
        );
    }

    private function scope(RestRequest $request): EventScope
    {
        return new EventScope($this->routeId($request, 'event_id'));
    }

    private function routeId(RestRequest $request, string $name): int
    {
        $candidate = $request->route($name);
        if ($candidate === null || !ctype_digit($candidate)) throw new RequestInputException('resource_not_found');
        $value = filter_var($candidate, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        if ($value === false) throw new RequestInputException('resource_not_found');
        return $value;
    }

    /** @param list<string> $allowed @return array<string, mixed> */
    private function only(RestRequest $request, array $allowed): array
    {
        $json = $request->json();
        if (array_diff(array_keys($json), $allowed) !== []) throw new RequestInputException('validation_failed');
        return $json;
    }

    private function requiredString(mixed $value): string
    {
        if (!is_string($value)) throw new RequestInputException('validation_failed');
        return trim($value);
    }

    private function positiveInt(mixed $value): int
    {
        if (!is_int($value) || $value < 1) throw new RequestInputException('validation_failed');
        return $value;
    }

    private function optionalPositiveInt(mixed $value): ?int
    {
        if ($value === null) return null;
        return $this->positiveInt($value);
    }

    private function optionalString(mixed $value): ?string
    {
        if ($value === null) return null;
        if (!is_string($value)) throw new RequestInputException('validation_failed');
        return trim($value);
    }
}
