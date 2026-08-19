<?php

namespace EventFlow\Presentation\Api;

use DateTimeImmutable;
use DateTimeZone;
use EventFlow\Application\Error\RequestId;
use EventFlow\Application\Idempotency\IdempotencyOutcome;
use EventFlow\Application\Seating\{RecommendedPlacement, StoredRecommendation};

final readonly class SeatingRecommendationPresenter
{
    public function resource(StoredRecommendation $recommendation, RequestId $requestId): JsonApiResponse
    {
        return new JsonApiResponse(200, ['data' => $this->data($recommendation), 'request_id' => $requestId->value], $this->headers($recommendation, $requestId));
    }

    public function outcome(IdempotencyOutcome $outcome, int $eventId, RequestId $requestId): JsonApiResponse
    {
        $recommendation = $outcome->response instanceof StoredRecommendation ? $outcome->response : null;
        $data = $recommendation === null ? ['type' => $outcome->reference->entityType, 'id' => $outcome->reference->entityId] : $this->data($recommendation);
        $headers = $recommendation === null ? $this->headers(null, $requestId) : $this->headers($recommendation, $requestId);
        $headers['Location'] = '/wp-json/eventflow/v1/events/' . $eventId . '/seating/recommendations/' . $outcome->reference->entityId;
        return new JsonApiResponse($outcome->reference->responseStatusCode, ['data' => $data, 'meta' => ['replayed' => $outcome->replayed], 'request_id' => $requestId->value], $headers);
    }

    /** @return array<string, mixed> */
    private function data(StoredRecommendation $recommendation): array
    {
        return [
            'id' => $recommendation->recommendationId,
            'event_id' => $recommendation->eventScope->eventId,
            'status' => $recommendation->status->value,
            'input_fingerprint' => $recommendation->plan->inputFingerprint,
            'algorithm_version' => $recommendation->plan->algorithmVersion,
            'seed' => $recommendation->plan->seed,
            'placements' => array_map($this->placement(...), $recommendation->plan->placements),
            'warnings' => $recommendation->plan->warnings,
            'created_at' => $this->date($recommendation->createdAt),
            'applied_at' => $recommendation->appliedAt === null ? null : $this->date($recommendation->appliedAt),
        ];
    }

    /** @return array<string, mixed> */
    private function placement(RecommendedPlacement $placement): array
    {
        return ['attendee_id' => $placement->attendeeId, 'table_id' => $placement->tableId, 'seat_id' => $placement->seatId, 'reason' => $placement->reason];
    }

    /** @return array<string, string> */
    private function headers(?StoredRecommendation $recommendation, RequestId $requestId): array
    {
        $headers = ['X-Request-ID' => $requestId->value, 'Cache-Control' => 'no-store, max-age=0', 'Pragma' => 'no-cache'];
        if ($recommendation !== null) {
            $value = implode(':', [$recommendation->recommendationId, $recommendation->status->value, $recommendation->plan->inputFingerprint, $recommendation->appliedAt?->format('U') ?? '']);
            $headers['ETag'] = '"' . hash('sha256', $value) . '"';
        }
        return $headers;
    }

    private function date(DateTimeImmutable $date): string { return $date->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d\TH:i:s\Z'); }
}
