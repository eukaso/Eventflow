<?php

namespace EventFlow\Presentation\Api;

use EventFlow\Application\Error\RequestId;
use EventFlow\Application\Idempotency\IdempotencyOutcome;
use EventFlow\Application\Seating\{RecommendationPlan, RecommendedPlacement, SeatingAssignment};

final readonly class SeatingPlanningPresenter
{
    public function recommendation(RecommendationPlan $plan, RequestId $requestId): JsonApiResponse
    {
        return new JsonApiResponse(
            200,
            [
                'data' => [
                    'input_fingerprint' => $plan->inputFingerprint,
                    'algorithm_version' => $plan->algorithmVersion,
                    'seed' => $plan->seed,
                    'placements' => array_map($this->placement(...), $plan->placements),
                    'warnings' => $plan->warnings,
                ],
                'request_id' => $requestId->value,
            ],
            ['X-Request-ID' => $requestId->value, 'Cache-Control' => 'no-store, max-age=0'],
        );
    }

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
    private function placement(RecommendedPlacement $placement): array
    {
        return [
            'attendee_id' => $placement->attendeeId,
            'table_id' => $placement->tableId,
            'seat_id' => $placement->seatId,
            'reason' => $placement->reason,
        ];
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
