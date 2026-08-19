<?php

namespace EventFlow\Presentation\Api;

use DateTimeZone;
use EventFlow\Application\Error\RequestId;
use EventFlow\Application\Idempotency\IdempotencyOutcome;
use EventFlow\Application\Membership\MembershipPage;
use EventFlow\Application\Membership\MembershipRecord;
use EventFlow\Application\Persistence\EventScope;

final readonly class MembershipPresenter
{
    public function outcome(IdempotencyOutcome $outcome, EventScope $scope, RequestId $requestId): JsonApiResponse
    {
        $data = $outcome->response instanceof MembershipRecord
            ? $this->membership($outcome->response)
            : ['type' => $outcome->reference->entityType, 'id' => $outcome->reference->entityId];
        return new JsonApiResponse(
            $outcome->reference->responseStatusCode,
            ['data' => $data, 'meta' => ['replayed' => $outcome->replayed], 'request_id' => $requestId->value],
            [
                'X-Request-ID' => $requestId->value,
                'Cache-Control' => 'no-store, max-age=0',
                'Location' => '/wp-json/eventflow/v1/events/' . $scope->eventId . '/memberships/' . $outcome->reference->entityId,
            ],
        );
    }

    public function page(MembershipPage $page, RequestId $requestId): JsonApiResponse
    {
        return new JsonApiResponse(
            200,
            [
                'data' => array_map($this->membership(...), $page->memberships),
                'meta' => ['next_after_membership_id' => $page->nextAfterMembershipId],
                'request_id' => $requestId->value,
            ],
            [
                'X-Request-ID' => $requestId->value,
                'Cache-Control' => 'no-store, max-age=0',
            ],
        );
    }

    /** @return array<string, mixed> */
    private function membership(MembershipRecord $membership): array
    {
        $utc = new DateTimeZone('UTC');
        return [
            'id' => $membership->membershipId,
            'event_id' => $membership->eventScope->eventId,
            'user_id' => $membership->userId,
            'role' => $membership->role->value,
            'status' => $membership->status->value,
            'is_primary_owner' => $membership->isPrimaryOwner,
            'expires_at' => $membership->expiresAt?->setTimezone($utc)->format('Y-m-d\TH:i:s\Z'),
        ];
    }
}
