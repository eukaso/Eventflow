<?php

namespace EventFlow\Presentation\Api;

use DateTimeZone;
use EventFlow\Application\Error\RequestId;
use EventFlow\Application\Event\EventPage;
use EventFlow\Application\Event\EventRecord;
use EventFlow\Application\Idempotency\IdempotencyOutcome;

final readonly class EventPresenter
{
    public function outcome(IdempotencyOutcome $outcome, RequestId $requestId): JsonApiResponse
    {
        $data = $outcome->response instanceof EventRecord
            ? $this->event($outcome->response)
            : ['type' => $outcome->reference->entityType, 'id' => $outcome->reference->entityId];
        $headers = [
            'X-Request-ID' => $requestId->value,
            'Cache-Control' => 'no-store, max-age=0',
            'Location' => '/wp-json/eventflow/v1/events/' . $outcome->reference->entityId,
        ];
        if ($outcome->response instanceof EventRecord) $headers['ETag'] = $this->etag($outcome->response);
        return new JsonApiResponse(
            $outcome->reference->responseStatusCode,
            ['data' => $data, 'meta' => ['replayed' => $outcome->replayed], 'request_id' => $requestId->value],
            $headers,
        );
    }

    public function resource(EventRecord $event, RequestId $requestId): JsonApiResponse
    {
        return new JsonApiResponse(
            200,
            ['data' => $this->event($event), 'request_id' => $requestId->value],
            ['X-Request-ID' => $requestId->value, 'Cache-Control' => 'no-store, max-age=0', 'ETag' => $this->etag($event)],
        );
    }

    public function page(EventPage $page, RequestId $requestId): JsonApiResponse
    {
        return new JsonApiResponse(
            200,
            [
                'data' => array_map($this->event(...), $page->events),
                'meta' => ['next_after_event_id' => $page->nextAfterEventId],
                'request_id' => $requestId->value,
            ],
            ['X-Request-ID' => $requestId->value, 'Cache-Control' => 'no-store, max-age=0'],
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
            'revision' => $event->revision,
        ];
    }

    private function etag(EventRecord $event): string
    {
        return '"' . $event->revision . '"';
    }
}
