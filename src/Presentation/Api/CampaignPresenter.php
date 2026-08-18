<?php

namespace EventFlow\Presentation\Api;

use EventFlow\Application\Communication\{CampaignQueueResult, CampaignRecord};
use EventFlow\Application\Error\RequestId;
use EventFlow\Application\Idempotency\IdempotencyOutcome;

final readonly class CampaignPresenter
{
    public function creation(IdempotencyOutcome $outcome, int $eventId, RequestId $requestId): JsonApiResponse
    {
        $data = $outcome->response instanceof CampaignRecord
            ? $this->campaign($outcome->response)
            : $this->reference($outcome);
        return $this->response($outcome, $data, $eventId, $requestId);
    }

    public function queue(IdempotencyOutcome $outcome, int $eventId, RequestId $requestId): JsonApiResponse
    {
        $result = $outcome->response;
        $data = $result instanceof CampaignQueueResult
            ? [
                'campaign_id' => $result->campaignId,
                'recipient_count' => $result->recipientCount,
                'message_ids' => array_map(static fn ($message): int => $message->messageId, $result->messages),
            ]
            : $this->reference($outcome);
        return $this->response($outcome, $data, $eventId, $requestId);
    }

    /** @param array<string, mixed> $data */
    private function response(IdempotencyOutcome $outcome, array $data, int $eventId, RequestId $requestId): JsonApiResponse
    {
        return new JsonApiResponse(
            $outcome->reference->responseStatusCode,
            ['data' => $data, 'meta' => ['replayed' => $outcome->replayed], 'request_id' => $requestId->value],
            [
                'X-Request-ID' => $requestId->value,
                'Cache-Control' => 'no-store, max-age=0',
                'Location' => '/wp-json/eventflow/v1/events/' . $eventId . '/campaigns/' . $outcome->reference->entityId,
            ],
        );
    }

    /** @return array<string, mixed> */
    private function campaign(CampaignRecord $campaign): array
    {
        return [
            'id' => $campaign->campaignId,
            'template_id' => $campaign->templateId,
            'name' => $campaign->name,
            'channel' => $campaign->channel->value,
            'purpose' => $campaign->purpose->value,
            'audience_mode' => $campaign->audienceMode->value,
            'audience' => $campaign->audienceDefinition,
            'status' => $campaign->status,
        ];
    }

    /** @return array{type: string, id: int} */
    private function reference(IdempotencyOutcome $outcome): array
    {
        return ['type' => $outcome->reference->entityType, 'id' => $outcome->reference->entityId];
    }
}
