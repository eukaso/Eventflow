<?php

namespace EventFlow\Presentation\Api;

use EventFlow\Application\Persistence\EventScope;

final readonly class AttendeeQueryRequestMapper
{
    public function scope(RestRequest $request): EventScope
    {
        return new EventScope($this->routeId($request, 'event_id'));
    }

    public function attendeeId(RestRequest $request): int
    {
        return $this->routeId($request, 'attendee_id');
    }

    /** @return array{int, ?int} */
    public function page(RestRequest $request): array
    {
        return [
            $this->queryInt($request->query('limit'), 50, 1, 100),
            $request->query('after') === null
                ? null
                : $this->queryInt($request->query('after'), null, 1, PHP_INT_MAX),
        ];
    }

    private function routeId(RestRequest $request, string $name): int
    {
        $candidate = $request->route($name);
        if ($candidate === null || !ctype_digit($candidate)) {
            throw new RequestInputException('resource_not_found');
        }
        $value = filter_var($candidate, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        if ($value === false) {
            throw new RequestInputException('resource_not_found');
        }
        return $value;
    }

    private function queryInt(?string $value, ?int $default, int $minimum, int $maximum): int
    {
        if ($value === null) {
            return $default ?? throw new RequestInputException('validation_failed');
        }
        if (!preg_match('/^[1-9][0-9]*$/', $value)) {
            throw new RequestInputException('validation_failed');
        }
        $integer = filter_var(
            $value,
            FILTER_VALIDATE_INT,
            ['options' => ['min_range' => $minimum, 'max_range' => $maximum]],
        );
        if ($integer === false) {
            throw new RequestInputException('validation_failed');
        }
        return $integer;
    }
}
