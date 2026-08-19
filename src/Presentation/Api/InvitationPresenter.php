<?php

namespace EventFlow\Presentation\Api;

use DateTimeZone;
use EventFlow\Application\Error\RequestId;
use EventFlow\Application\Idempotency\IdempotencyOutcome;
use EventFlow\Application\Invitation\{InvitationPage, InvitationRecord, IssuedInvitation};
use EventFlow\Application\Persistence\EventScope;

final readonly class InvitationPresenter
{
    public function outcome(IdempotencyOutcome $outcome, EventScope $scope, RequestId $requestId): JsonApiResponse
    {
        $response = $outcome->response;
        $invitation = $response instanceof IssuedInvitation ? $response->invitation : $response;
        $data = $invitation instanceof InvitationRecord
            ? $this->invitation($invitation)
            : ['type' => $outcome->reference->entityType, 'id' => $outcome->reference->entityId];
        if ($response instanceof IssuedInvitation) {
            $data['credential'] = ['token' => $response->rawToken, 'return_once' => true];
        }
        $headers = $this->headers($requestId, $invitation instanceof InvitationRecord ? $invitation : null);
        $headers['Location'] = '/wp-json/eventflow/v1/events/' . $scope->eventId . '/invitations/' . $outcome->reference->entityId;
        return new JsonApiResponse(
            $outcome->reference->responseStatusCode,
            ['data' => $data, 'meta' => ['replayed' => $outcome->replayed], 'request_id' => $requestId->value],
            $headers,
        );
    }

    public function page(InvitationPage $page, RequestId $requestId): JsonApiResponse
    {
        return new JsonApiResponse(
            200,
            [
                'data' => array_map($this->invitation(...), $page->invitations),
                'meta' => ['next_after_invitation_id' => $page->nextAfterInvitationId],
                'request_id' => $requestId->value,
            ],
            $this->headers($requestId),
        );
    }

    public function resource(InvitationRecord $invitation, RequestId $requestId): JsonApiResponse
    {
        return new JsonApiResponse(
            200,
            ['data' => $this->invitation($invitation), 'request_id' => $requestId->value],
            $this->headers($requestId, $invitation),
        );
    }

    /** @return array<string, mixed> */
    private function invitation(InvitationRecord $invitation): array
    {
        $utc = new DateTimeZone('UTC');
        return [
            'id' => $invitation->invitationId,
            'event_id' => $invitation->eventScope->eventId,
            'code' => $invitation->code,
            'primary_name' => $invitation->primaryName,
            'primary_email' => $invitation->primaryEmail,
            'primary_phone' => $invitation->primaryPhone,
            'capacity' => $invitation->capacity,
            'status' => $invitation->status->value,
            'response_status' => $invitation->responseStatus,
            'organizer_notes' => $invitation->organizerNotes,
            'token_version' => $invitation->tokenVersion,
            'token_expires_at' => $invitation->tokenExpiresAt?->setTimezone($utc)->format('Y-m-d\TH:i:s\Z'),
            'revision' => $invitation->revision,
            'archived_at' => $invitation->archivedAt?->setTimezone($utc)->format('Y-m-d\TH:i:s\Z'),
        ];
    }

    /** @return array<string, string> */
    private function headers(RequestId $requestId, ?InvitationRecord $invitation = null): array
    {
        $headers = [
            'X-Request-ID' => $requestId->value,
            'Cache-Control' => 'no-store, max-age=0',
        ];
        if ($invitation !== null) {
            $headers['ETag'] = '"' . $invitation->revision . '"';
        }
        return $headers;
    }
}
