<?php

namespace EventFlow\Presentation\Api;

use EventFlow\Application\CheckIn\CheckInMethod;
use EventFlow\Application\Persistence\EventScope;

final readonly class CheckInRequestMapper
{
    public function search(RestRequest $request): ReceptionSearchInput
    {
        $query = $request->query('q');
        $limit = $request->query('limit');
        if ($query === null) throw new RequestInputException('validation_failed');
        return new ReceptionSearchInput($this->scope($request), trim($query), $limit === null ? 20 : $this->queryInt($limit));
    }

    public function lookup(RestRequest $request): ReceptionLookupInput
    {
        $code = strtolower(trim((string) ($request->query('code') ?? '')));
        if (!preg_match('/^[a-f0-9]{64}$/', $code)) throw new RequestInputException('validation_failed');
        return new ReceptionLookupInput($this->scope($request), $code);
    }

    public function individual(RestRequest $request): CheckInInput
    {
        $json = $this->only($request, ['attendee_id', 'station_id', 'method', 'notes']);
        return $this->checkInInput($request, [$this->positiveInt($json['attendee_id'] ?? null)], $json);
    }

    public function bulk(RestRequest $request): CheckInInput
    {
        $json = $this->only($request, ['attendee_ids', 'station_id', 'method', 'notes']);
        $ids = $json['attendee_ids'] ?? null;
        if (!is_array($ids) || !array_is_list($ids)) throw new RequestInputException('validation_failed');
        return $this->checkInInput($request, array_map($this->positiveInt(...), $ids), $json);
    }

    public function reversal(RestRequest $request): CheckInReversalInput
    {
        $json = $this->only($request, ['reason']);
        return new CheckInReversalInput(
            $this->scope($request),
            $this->routeId($request, 'checkin_id'),
            $this->requiredString($json['reason'] ?? null),
        );
    }

    /** @param list<int> $ids @param array<string, mixed> $json */
    private function checkInInput(RestRequest $request, array $ids, array $json): CheckInInput
    {
        $method = $json['method'] ?? null;
        if (!is_string($method)) throw new RequestInputException('validation_failed');
        $parsedMethod = CheckInMethod::tryFrom($method);
        if ($parsedMethod === null) throw new RequestInputException('validation_failed');
        return new CheckInInput(
            $this->scope($request),
            $ids,
            $this->optionalPositiveInt($json['station_id'] ?? null),
            $parsedMethod,
            $this->optionalString($json['notes'] ?? null),
        );
    }

    private function scope(RestRequest $request): EventScope { return new EventScope($this->routeId($request, 'event_id')); }

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

    private function positiveInt(mixed $value): int
    {
        if (!is_int($value) || $value < 1) throw new RequestInputException('validation_failed');
        return $value;
    }

    private function optionalPositiveInt(mixed $value): ?int { return $value === null ? null : $this->positiveInt($value); }

    private function requiredString(mixed $value): string
    {
        if (!is_string($value)) throw new RequestInputException('validation_failed');
        return trim($value);
    }

    private function optionalString(mixed $value): ?string
    {
        if ($value === null) return null;
        return $this->requiredString($value);
    }

    private function queryInt(string $value): int
    {
        if (!ctype_digit($value)) throw new RequestInputException('validation_failed');
        $parsed = filter_var($value, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 50]]);
        if ($parsed === false) throw new RequestInputException('validation_failed');
        return $parsed;
    }
}
