<?php

namespace EventFlow\Presentation\Api;

use EventFlow\Application\Attendee\{AttendeePage, AttendeeRecord};
use EventFlow\Application\Error\RequestId;
use EventFlow\Application\Idempotency\IdempotencyOutcome;

final readonly class AttendeePresenter
{
    public function outcome(IdempotencyOutcome $outcome, int $eventId, RequestId $requestId): JsonApiResponse
    {
        $data = $outcome->response instanceof AttendeeRecord
            ? $this->attendee($outcome->response)
            : ['type' => $outcome->reference->entityType, 'id' => $outcome->reference->entityId];
        return new JsonApiResponse(
            $outcome->reference->responseStatusCode,
            ['data' => $data, 'meta' => ['replayed' => $outcome->replayed], 'request_id' => $requestId->value],
            [
                'X-Request-ID' => $requestId->value,
                'Cache-Control' => 'no-store, max-age=0',
                'Location' => '/wp-json/eventflow/v1/events/' . $eventId . '/attendees/' . $outcome->reference->entityId,
            ],
        );
    }

    public function page(AttendeePage $page, RequestId $requestId): JsonApiResponse
    {
        return new JsonApiResponse(
            200,
            [
                'data' => array_map($this->attendee(...), $page->attendees),
                'meta' => ['next_after_attendee_id' => $page->nextAfterAttendeeId],
                'request_id' => $requestId->value,
            ],
            $this->headers($requestId),
        );
    }

    public function resource(AttendeeRecord $attendee, RequestId $requestId): JsonApiResponse
    {
        return new JsonApiResponse(
            200,
            ['data' => $this->attendee($attendee), 'request_id' => $requestId->value],
            $this->headers($requestId),
        );
    }

    /** @return array<string, mixed> */
    private function attendee(AttendeeRecord $attendee): array
    {
        return [
            'id' => $attendee->attendeeId,
            'event_id' => $attendee->eventScope->eventId,
            'invitation_id' => $attendee->invitationId,
            'display_name' => $attendee->displayName,
            'role' => $attendee->role->value,
            'status' => $attendee->status->value,
            'email' => $attendee->email,
            'phone' => $attendee->phone,
            'dietary_requirements' => $attendee->dietaryRequirements,
            'accessibility_requirements' => $attendee->accessibilityRequirements,
        ];
    }

    /** @return array<string, string> */
    private function headers(RequestId $requestId): array
    {
        return [
            'X-Request-ID' => $requestId->value,
            'Cache-Control' => 'no-store, max-age=0',
        ];
    }
}
