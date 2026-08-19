<?php

namespace EventFlow\Presentation\Api;

use EventFlow\Application\Error\RequestId;
use EventFlow\Application\Idempotency\IdempotencyOutcome;
use EventFlow\Application\Seating\{SeatingAssignment, SeatingGroupMove};

final readonly class SeatingGroupMovePresenter
{
    public function outcome(IdempotencyOutcome $outcome, int $eventId, int $groupId, RequestId $requestId): JsonApiResponse
    {
        $move = $outcome->response instanceof SeatingGroupMove ? $outcome->response : null;
        $data = $move === null ? ['type' => $outcome->reference->entityType, 'id' => $outcome->reference->entityId] : $this->move($move);
        $headers = [
            'X-Request-ID' => $requestId->value,
            'Cache-Control' => 'no-store, max-age=0',
            'Pragma' => 'no-cache',
            'Location' => '/wp-json/eventflow/v1/events/' . $eventId . '/seating-groups/' . $groupId,
        ];
        if ($move !== null) $headers['ETag'] = '"' . hash('sha256', (string) json_encode($data, JSON_THROW_ON_ERROR)) . '"';
        return new JsonApiResponse(
            $outcome->reference->responseStatusCode,
            ['data' => $data, 'meta' => ['replayed' => $outcome->replayed], 'request_id' => $requestId->value],
            $headers,
        );
    }

    /** @return array<string, mixed> */
    private function move(SeatingGroupMove $move): array
    {
        return [
            'group_id' => $move->groupId,
            'table_id' => $move->tableId,
            'assignments' => array_map($this->assignment(...), $move->assignments),
            'required_group_override' => $move->requiredGroupOverride,
            'override_reason' => $move->overrideReason,
        ];
    }

    /** @return array<string, mixed> */
    private function assignment(SeatingAssignment $assignment): array
    {
        return [
            'id' => $assignment->assignmentId,
            'attendee_id' => $assignment->attendeeId,
            'table_id' => $assignment->tableId,
            'seat_id' => $assignment->seatId,
            'source' => $assignment->source,
            'group_override' => $assignment->groupOverride,
            'override_reason' => $assignment->overrideReason,
        ];
    }
}
