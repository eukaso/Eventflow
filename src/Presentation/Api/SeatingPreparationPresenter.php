<?php

namespace EventFlow\Presentation\Api;

use EventFlow\Application\Error\RequestId;
use EventFlow\Application\Idempotency\IdempotencyOutcome;
use EventFlow\Application\Seating\{ConfiguredTable, SeatingGroup, SeatingReadiness, SeatingSeat};

final readonly class SeatingPreparationPresenter
{
    public function outcome(IdempotencyOutcome $outcome, int $eventId, string $collection, RequestId $requestId): JsonApiResponse
    {
        $response = $outcome->response;
        $data = match (true) {
            $response instanceof ConfiguredTable => $this->table($response),
            $response instanceof SeatingGroup => $this->group($response),
            default => ['type' => $outcome->reference->entityType, 'id' => $outcome->reference->entityId],
        };
        return new JsonApiResponse(
            $outcome->reference->responseStatusCode,
            ['data' => $data, 'meta' => ['replayed' => $outcome->replayed], 'request_id' => $requestId->value],
            [
                'X-Request-ID' => $requestId->value,
                'Cache-Control' => 'no-store, max-age=0',
                'Location' => '/wp-json/eventflow/v1/events/' . $eventId . '/' . $collection . '/' . $outcome->reference->entityId,
            ],
        );
    }

    public function readiness(SeatingReadiness $readiness, RequestId $requestId): JsonApiResponse
    {
        return new JsonApiResponse(
            200,
            [
                'data' => [
                    'ready' => $readiness->ready,
                    'errors' => $readiness->errors,
                    'warnings' => $readiness->warnings,
                    'input_fingerprint' => $readiness->fingerprint,
                ],
                'request_id' => $requestId->value,
            ],
            ['X-Request-ID' => $requestId->value, 'Cache-Control' => 'no-store, max-age=0'],
        );
    }

    /** @return array<string, mixed> */
    private function table(ConfiguredTable $configured): array
    {
        return [
            'id' => $configured->table->tableId,
            'name' => $configured->table->name,
            'capacity' => $configured->table->capacity,
            'sort_order' => $configured->table->sortOrder,
            'seats' => array_map($this->seat(...), $configured->seats),
        ];
    }

    /** @return array<string, mixed> */
    private function seat(SeatingSeat $seat): array
    {
        return [
            'id' => $seat->seatId,
            'label' => $seat->label,
            'accessible' => $seat->accessible,
            'sort_order' => $seat->sortOrder,
        ];
    }

    /** @return array<string, mixed> */
    private function group(SeatingGroup $group): array
    {
        return [
            'id' => $group->groupId,
            'name' => $group->name,
            'constraint_level' => $group->constraintLevel->value,
            'priority' => $group->priority,
            'attendee_ids' => $group->attendeeIds,
        ];
    }
}
