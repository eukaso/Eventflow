<?php

namespace EventFlow\Presentation\Api;

use EventFlow\Application\Persistence\EventScope;
use EventFlow\Application\Seating\SeatingGroupMoveMember;

final readonly class SeatingGroupMoveRequestMapper
{
    public function move(RestRequest $request, int $expectedGroupRevision): SeatingGroupMoveInput
    {
        $json = $this->only($request, ['table_id', 'members', 'override_required_groups', 'override_reason']);
        if (!array_key_exists('table_id', $json) || !array_key_exists('members', $json) || !is_array($json['members']) || !array_is_list($json['members']) || $json['members'] === []) {
            throw new RequestInputException('validation_failed');
        }
        $members = array_map($this->member(...), $json['members']);
        $override = $json['override_required_groups'] ?? false;
        if (!is_bool($override)) throw new RequestInputException('validation_failed');
        $reason = $this->optionalString($json['override_reason'] ?? null);
        if (($override && ($reason === null || $reason === '')) || (!$override && $reason !== null)) throw new RequestInputException('validation_failed');

        return new SeatingGroupMoveInput(
            new EventScope($this->routeId($request, 'event_id')),
            $this->routeId($request, 'group_id'),
            $this->positiveInt($json['table_id']),
            $expectedGroupRevision,
            $members,
            $override,
            $reason,
        );
    }

    private function member(mixed $value): SeatingGroupMoveMember
    {
        if (!is_array($value) || array_is_list($value)) throw new RequestInputException('validation_failed');
        $required = ['attendee_id', 'seat_id', 'expected_assignment_id'];
        if (array_diff(array_keys($value), $required) !== [] || array_diff($required, array_keys($value)) !== []) throw new RequestInputException('validation_failed');
        return new SeatingGroupMoveMember(
            $this->positiveInt($value['attendee_id']),
            $this->optionalPositiveInt($value['seat_id']),
            $this->optionalPositiveInt($value['expected_assignment_id']),
        );
    }

    /** @param list<string> $allowed @return array<string, mixed> */
    private function only(RestRequest $request, array $allowed): array
    {
        $json = $request->json();
        if (array_diff(array_keys($json), $allowed) !== []) throw new RequestInputException('validation_failed');
        return $json;
    }

    private function routeId(RestRequest $request, string $name): int
    {
        $candidate = $request->route($name);
        if ($candidate === null || !ctype_digit($candidate)) throw new RequestInputException('resource_not_found');
        $id = filter_var($candidate, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        if ($id === false) throw new RequestInputException('resource_not_found');
        return $id;
    }

    private function positiveInt(mixed $value): int
    {
        if (!is_int($value) || $value < 1) throw new RequestInputException('validation_failed');
        return $value;
    }

    private function optionalPositiveInt(mixed $value): ?int
    {
        return $value === null ? null : $this->positiveInt($value);
    }

    private function optionalString(mixed $value): ?string
    {
        if ($value === null) return null;
        if (!is_string($value)) throw new RequestInputException('validation_failed');
        return trim($value);
    }
}
