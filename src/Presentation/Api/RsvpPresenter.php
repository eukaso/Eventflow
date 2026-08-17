<?php

namespace EventFlow\Presentation\Api;

use EventFlow\Application\Attendee\{AttendeeRecord, RsvpResult};
use EventFlow\Application\Error\RequestId;
use EventFlow\Application\Idempotency\IdempotencyOutcome;

final readonly class RsvpPresenter
{
    public function outcome(IdempotencyOutcome $outcome, RequestId $requestId): JsonApiResponse
    {
        $result = $outcome->response;
        $data = $result instanceof RsvpResult
            ? [
                'invitation_id' => $result->invitation->invitationId,
                'response_status' => $result->invitation->responseStatus->value,
                'response_revision' => $result->invitation->responseRevision,
                'capacity' => $result->invitation->capacity,
                'attendees' => array_map($this->attendee(...), $result->attendees),
            ]
            : ['type' => $outcome->reference->entityType, 'id' => $outcome->reference->entityId];
        $headers = [
            'X-Request-ID' => $requestId->value,
            'Cache-Control' => 'no-store, max-age=0',
            'Pragma' => 'no-cache',
        ];
        if ($result instanceof RsvpResult) $headers['ETag'] = '"' . $result->invitation->responseRevision . '"';
        return new JsonApiResponse(
            $outcome->reference->responseStatusCode,
            ['data' => $data, 'meta' => ['replayed' => $outcome->replayed], 'request_id' => $requestId->value],
            $headers,
        );
    }

    /** @return array<string, mixed> */
    private function attendee(AttendeeRecord $attendee): array
    {
        return [
            'id' => $attendee->attendeeId,
            'display_name' => $attendee->displayName,
            'role' => $attendee->role->value,
            'status' => $attendee->status->value,
            'email' => $attendee->email,
            'phone' => $attendee->phone,
            'dietary_requirements' => $attendee->dietaryRequirements,
            'accessibility_requirements' => $attendee->accessibilityRequirements,
        ];
    }
}
