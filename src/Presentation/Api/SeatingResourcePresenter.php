<?php

namespace EventFlow\Presentation\Api;

use EventFlow\Application\Error\RequestId;
use EventFlow\Application\Idempotency\IdempotencyOutcome;
use EventFlow\Application\Seating\{ConfiguredTable, SeatingGroup, SeatingSeat, SeatingSnapshot, SeatingTable};

final readonly class SeatingResourcePresenter
{
    public function tables(SeatingSnapshot $snapshot, RequestId $requestId): JsonApiResponse
    {
        $attendees = [];
        foreach ($snapshot->attendees as $attendee) $attendees[$attendee->attendeeId] = $attendee->displayName;
        $assignments = [];
        foreach ($snapshot->assignments as $assignment) {
            $assignments[$assignment->tableId][] = [
                'assignment_id' => $assignment->assignmentId,
                'attendee_id' => $assignment->attendeeId,
                'attendee_name' => $attendees[$assignment->attendeeId] ?? ('Attendee ' . $assignment->attendeeId),
                'seat_id' => $assignment->seatId,
                'source' => $assignment->source,
            ];
        }
        $tables = array_map(function (SeatingTable $table) use ($assignments): array {
            $assigned = $assignments[$table->tableId] ?? [];
            return [...$this->table($table), 'occupancy' => count($assigned), 'assigned_attendees' => $assigned];
        }, $snapshot->tables);
        return $this->response(200, ['data' => $tables, 'request_id' => $requestId->value], $requestId);
    }

    public function tableDetail(ConfiguredTable $table, RequestId $requestId): JsonApiResponse
    {
        return $this->response(200, ['data' => $this->configuredTable($table), 'request_id' => $requestId->value], $requestId, $table->table->revision);
    }

    public function groups(SeatingSnapshot $snapshot, RequestId $requestId): JsonApiResponse
    {
        return $this->response(200, ['data' => array_map($this->group(...), $snapshot->groups), 'request_id' => $requestId->value], $requestId);
    }

    public function groupDetail(SeatingGroup $group, RequestId $requestId): JsonApiResponse
    {
        return $this->response(200, ['data' => $this->group($group), 'request_id' => $requestId->value], $requestId, $group->revision);
    }

    /** @param list<SeatingSeat> $seats */
    public function seats(array $seats, RequestId $requestId): JsonApiResponse
    {
        return $this->response(200, ['data' => array_map($this->seat(...), $seats), 'request_id' => $requestId->value], $requestId);
    }

    public function seatDetail(SeatingSeat $seat, RequestId $requestId): JsonApiResponse
    {
        return $this->response(200, ['data' => $this->seat($seat), 'request_id' => $requestId->value], $requestId, $seat->revision);
    }

    public function outcome(IdempotencyOutcome $outcome, RequestId $requestId, ?string $location = null): JsonApiResponse
    {
        $result = $outcome->response;
        $data = match (true) {
            $result instanceof ConfiguredTable => $this->configuredTable($result),
            $result instanceof SeatingSeat => $this->seat($result),
            $result instanceof SeatingGroup => $this->group($result),
            default => ['type' => $outcome->reference->entityType, 'id' => $outcome->reference->entityId],
        };
        $revision = match (true) {
            $result instanceof ConfiguredTable => $result->table->revision,
            $result instanceof SeatingSeat, $result instanceof SeatingGroup => $result->revision,
            default => null,
        };
        $response = $this->response($outcome->reference->responseStatusCode, ['data' => $data, 'meta' => ['replayed' => $outcome->replayed], 'request_id' => $requestId->value], $requestId, $revision);
        if ($location === null) return $response;
        return new JsonApiResponse($response->status(), $response->body(), [...$response->headers(), 'Location' => $location]);
    }

    /** @return array<string, mixed> */
    private function configuredTable(ConfiguredTable $configured): array
    {
        return [...$this->table($configured->table), 'seats' => array_map($this->seat(...), $configured->seats)];
    }

    /** @return array<string, mixed> */
    private function table(SeatingTable $table): array
    {
        return ['id' => $table->tableId, 'name' => $table->name, 'capacity' => $table->capacity, 'sort_order' => $table->sortOrder, 'revision' => $table->revision];
    }

    /** @return array<string, mixed> */
    private function seat(SeatingSeat $seat): array
    {
        return ['id' => $seat->seatId, 'table_id' => $seat->tableId, 'label' => $seat->label, 'accessible' => $seat->accessible, 'sort_order' => $seat->sortOrder, 'revision' => $seat->revision];
    }

    /** @return array<string, mixed> */
    private function group(SeatingGroup $group): array
    {
        return ['id' => $group->groupId, 'name' => $group->name, 'category' => $group->category, 'source' => $group->source, 'constraint_level' => $group->constraintLevel->value, 'priority' => $group->priority, 'attendee_ids' => $group->attendeeIds, 'revision' => $group->revision];
    }

    /** @param array<string, mixed> $body */
    private function response(int $status, array $body, RequestId $requestId, ?int $revision = null): JsonApiResponse
    {
        $headers = ['X-Request-ID' => $requestId->value, 'Cache-Control' => 'no-store, max-age=0', 'Pragma' => 'no-cache'];
        if ($revision !== null) $headers['ETag'] = '"' . $revision . '"';
        return new JsonApiResponse($status, $body, $headers);
    }
}
