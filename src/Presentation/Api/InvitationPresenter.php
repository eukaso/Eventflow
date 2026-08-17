<?php

namespace EventFlow\Presentation\Api;

use DateTimeZone;
use EventFlow\Application\Error\RequestId;
use EventFlow\Application\Idempotency\IdempotencyOutcome;
use EventFlow\Application\Invitation\{InvitationRecord, IssuedInvitation};
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
        return new JsonApiResponse(
            $outcome->reference->responseStatusCode,
            ['data' => $data, 'meta' => ['replayed' => $outcome->replayed], 'request_id' => $requestId->value],
            [
                'X-Request-ID' => $requestId->value,
                'Cache-Control' => 'no-store, max-age=0',
                'Location' => '/wp-json/eventflow/v1/events/' . $scope->eventId . '/invitations/' . $outcome->reference->entityId,
            ],
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
            'capacity' => $invitation->capacity,
            'status' => $invitation->status->value,
            'token_version' => $invitation->tokenVersion,
            'token_expires_at' => $invitation->tokenExpiresAt?->setTimezone($utc)->format('Y-m-d\TH:i:s\Z'),
        ];
    }
}
