<?php

namespace EventFlow\Presentation\Api;

use DateTimeZone;
use EventFlow\Application\Error\RequestId;
use EventFlow\Application\Event\EventRecord;
use EventFlow\Application\Idempotency\IdempotencyOutcome;

final readonly class EventPresenter
{
    public function outcome(IdempotencyOutcome $outcome, RequestId $requestId): JsonApiResponse
    {
        $data = $outcome->response instanceof EventRecord
            ? $this->event($outcome->response)
            : ['type' => $outcome->reference->entityType, 'id' => $outcome->reference->entityId];
        return new JsonApiResponse(
            $outcome->reference->responseStatusCode,
            ['data' => $data, 'meta' => ['replayed' => $outcome->replayed], 'request_id' => $requestId->value],
            [
                'X-Request-ID' => $requestId->value,
                'Cache-Control' => 'no-store, max-age=0',
                'Location' => '/wp-json/eventflow/v1/events/' . $outcome->reference->entityId,
            ],
        );
    }

    /** @return array<string, mixed> */
    private function event(EventRecord $event): array
    {
        $utc = new DateTimeZone('UTC');
        return [
            'id' => $event->scope->eventId,
            'name' => $event->name,
            'slug' => $event->slug,
            'status' => $event->status->value,
            'timezone' => $event->timezone,
            'starts_at' => $event->startsAt?->setTimezone($utc)->format('Y-m-d\TH:i:s\Z'),
            'ends_at' => $event->endsAt?->setTimezone($utc)->format('Y-m-d\TH:i:s\Z'),
            'venue_id' => $event->venueId,
        ];
    }
}
