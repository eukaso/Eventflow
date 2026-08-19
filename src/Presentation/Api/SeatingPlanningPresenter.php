<?php

namespace EventFlow\Presentation\Api;

use EventFlow\Application\Error\RequestId;
use EventFlow\Application\Idempotency\IdempotencyOutcome;
use EventFlow\Application\Seating\SeatingAssignment;

final readonly class SeatingPlanningPresenter
{
    public function assignment(IdempotencyOutcome $outcome, int $eventId, int $attendeeId, RequestId $requestId): JsonApiResponse
    {
        $data = $outcome->response instanceof SeatingAssignment
            ? $this->assigned($outcome->response)
            : ['type' => $outcome->reference->entityType, 'id' => $outcome->reference->entityId];
        return new JsonApiResponse(
            $outcome->reference->responseStatusCode,
            ['data' => $data, 'meta' => ['replayed' => $outcome->replayed], 'request_id' => $requestId->value],
            [
                'X-Request-ID' => $requestId->value,
                'Cache-Control' => 'no-store, max-age=0',
                'Location' => '/wp-json/eventflow/v1/events/' . $eventId . '/attendees/' . $attendeeId . '/seating',
            ],
        );
    }

    /** @return array<string, mixed> */
    private function assigned(SeatingAssignment $assignment): array
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
