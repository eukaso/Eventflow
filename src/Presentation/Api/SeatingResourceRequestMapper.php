<?php

namespace EventFlow\Presentation\Api;

use EventFlow\Application\Persistence\EventScope;
use EventFlow\Application\Seating\{ConfiguredTable, ConstraintLevel, SeatingGroup, SeatingGroupReplacement, SeatingSeat, SeatingSeatReplacement, SeatingTableReplacement};

final readonly class SeatingResourceRequestMapper
{
    public function scope(RestRequest $request): EventScope { return new EventScope($this->routeId($request, 'event_id')); }
    public function tableId(RestRequest $request): int { return $this->routeId($request, 'table_id'); }
    public function seatId(RestRequest $request): int { return $this->routeId($request, 'seat_id'); }
    public function groupId(RestRequest $request): int { return $this->routeId($request, 'group_id'); }

    public function tableReplacement(RestRequest $request, ConfiguredTable $current, int $expectedRevision): SeatingTableReplacement
    {
        $json = $this->patch($request, ['name', 'capacity', 'sort_order']);
        return new SeatingTableReplacement(
            array_key_exists('name', $json) ? $this->string($json['name']) : $current->table->name,
            array_key_exists('capacity', $json) ? $this->integer($json['capacity'], 1, 65535) : $current->table->capacity,
            array_key_exists('sort_order', $json) ? $this->integer($json['sort_order'], 0, PHP_INT_MAX) : $current->table->sortOrder,
            $expectedRevision,
        );
    }

    public function seatCreate(RestRequest $request): SeatingSeatInput
    {
        $json = $this->only($request, ['label', 'accessible', 'sort_order']);
        if (!array_key_exists('label', $json)) throw new RequestInputException('validation_failed');
        return new SeatingSeatInput(
            $this->scope($request),
            $this->tableId($request),
            $this->string($json['label']),
            array_key_exists('accessible', $json) ? $this->boolean($json['accessible']) : false,
            array_key_exists('sort_order', $json) ? $this->integer($json['sort_order'], 0, 65535) : 100,
        );
    }

    public function seatReplacement(RestRequest $request, SeatingSeat $current, int $expectedRevision): SeatingSeatReplacement
    {
        $json = $this->patch($request, ['label', 'accessible', 'sort_order']);
        return new SeatingSeatReplacement(
            array_key_exists('label', $json) ? $this->string($json['label']) : $current->label,
            array_key_exists('accessible', $json) ? $this->boolean($json['accessible']) : $current->accessible,
            array_key_exists('sort_order', $json) ? $this->integer($json['sort_order'], 0, 65535) : $current->sortOrder,
            $expectedRevision,
        );
    }

    public function groupReplacement(RestRequest $request, SeatingGroup $current, int $expectedRevision): SeatingGroupReplacement
    {
        $json = $this->patch($request, ['name', 'category', 'constraint_level', 'priority', 'attendee_ids']);
        $constraint = $current->constraintLevel;
        if (array_key_exists('constraint_level', $json)) {
            $constraint = is_string($json['constraint_level']) ? ConstraintLevel::tryFrom($json['constraint_level']) : null;
            if ($constraint === null) throw new RequestInputException('validation_failed');
        }
        $ids = $current->attendeeIds;
        if (array_key_exists('attendee_ids', $json)) {
            if (!is_array($json['attendee_ids']) || !array_is_list($json['attendee_ids']) || $json['attendee_ids'] === []) throw new RequestInputException('validation_failed');
            $ids = array_map(fn (mixed $id): int => $this->integer($id, 1, PHP_INT_MAX), $json['attendee_ids']);
        }
        return new SeatingGroupReplacement(
            array_key_exists('name', $json) ? $this->string($json['name']) : $current->name,
            array_key_exists('category', $json) ? $this->string($json['category']) : $current->category,
            $constraint,
            array_key_exists('priority', $json) ? $this->integer($json['priority'], 0, 65535) : $current->priority,
            $ids,
            $expectedRevision,
        );
    }

    /** @param list<string> $allowed @return array<string, mixed> */
    private function patch(RestRequest $request, array $allowed): array
    {
        $json = $this->only($request, $allowed);
        if ($json === []) throw new RequestInputException('validation_failed');
        return $json;
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

    private function string(mixed $value): string { if (!is_string($value)) throw new RequestInputException('validation_failed'); return trim($value); }
    private function boolean(mixed $value): bool { if (!is_bool($value)) throw new RequestInputException('validation_failed'); return $value; }
    private function integer(mixed $value, int $minimum, int $maximum): int { if (!is_int($value) || $value < $minimum || $value > $maximum) throw new RequestInputException('validation_failed'); return $value; }
}
