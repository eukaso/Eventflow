<?php

namespace EventFlow\Presentation\Api;

use EventFlow\Application\Persistence\EventScope;
use EventFlow\Application\Seating\ConstraintLevel;

final readonly class SeatingPreparationRequestMapper
{
    public function table(RestRequest $request): SeatingTableInput
    {
        $json = $this->only($request, ['name', 'capacity', 'seats']);
        $source = $json['seats'] ?? [];
        if (!is_array($source) || !array_is_list($source)) throw new RequestInputException('validation_failed');
        $seats = [];
        foreach ($source as $seat) {
            if (!is_array($seat) || array_is_list($seat) || array_diff(array_keys($seat), ['label', 'accessible']) !== []) {
                throw new RequestInputException('validation_failed');
            }
            $label = $seat['label'] ?? null;
            $accessible = $seat['accessible'] ?? false;
            if (!is_string($label) || !is_bool($accessible)) throw new RequestInputException('validation_failed');
            $seats[] = ['label' => trim($label), 'accessible' => $accessible];
        }
        return new SeatingTableInput(
            $this->scope($request),
            $this->requiredString($json['name'] ?? null),
            $this->boundedInt($json['capacity'] ?? null, 1, 65535),
            $seats,
        );
    }

    public function group(RestRequest $request): SeatingGroupInput
    {
        $json = $this->only($request, ['name', 'category', 'constraint_level', 'priority', 'attendee_ids']);
        $constraint = is_string($json['constraint_level'] ?? null) ? ConstraintLevel::tryFrom($json['constraint_level']) : null;
        $source = $json['attendee_ids'] ?? null;
        if ($constraint === null || !is_array($source) || !array_is_list($source) || $source === []) {
            throw new RequestInputException('validation_failed');
        }
        $ids = [];
        foreach ($source as $id) $ids[] = $this->boundedInt($id, 1, PHP_INT_MAX);
        return new SeatingGroupInput(
            $this->scope($request),
            $this->requiredString($json['name'] ?? null),
            $this->requiredString($json['category'] ?? null),
            $constraint,
            $this->boundedInt($json['priority'] ?? null, 0, 65535),
            $ids,
        );
    }

    public function scope(RestRequest $request): EventScope
    {
        $candidate = $request->route('event_id');
        if ($candidate === null || !ctype_digit($candidate)) throw new RequestInputException('resource_not_found');
        $value = filter_var($candidate, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        if ($value === false) throw new RequestInputException('resource_not_found');
        return new EventScope($value);
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

    private function boundedInt(mixed $value, int $minimum, int $maximum): int
    {
        if (!is_int($value) || $value < $minimum || $value > $maximum) throw new RequestInputException('validation_failed');
        return $value;
    }
}
