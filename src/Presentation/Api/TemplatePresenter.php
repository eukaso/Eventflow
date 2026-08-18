<?php

namespace EventFlow\Presentation\Api;

use EventFlow\Application\Communication\TemplateRecord;
use EventFlow\Application\Error\RequestId;
use EventFlow\Application\Idempotency\IdempotencyOutcome;

final readonly class TemplatePresenter
{
    public function outcome(IdempotencyOutcome $outcome, int $eventId, RequestId $requestId): JsonApiResponse
    {
        $data = $outcome->response instanceof TemplateRecord
            ? $this->template($outcome->response)
            : ['type' => $outcome->reference->entityType, 'id' => $outcome->reference->entityId];
        return new JsonApiResponse(
            $outcome->reference->responseStatusCode,
            ['data' => $data, 'meta' => ['replayed' => $outcome->replayed], 'request_id' => $requestId->value],
            [
                'X-Request-ID' => $requestId->value,
                'Cache-Control' => 'no-store, max-age=0',
                'Location' => '/wp-json/eventflow/v1/events/' . $eventId . '/communication-templates/' . $outcome->reference->entityId,
            ],
        );
    }

    /** @return array<string, mixed> */
    private function template(TemplateRecord $template): array
    {
        return [
            'id' => $template->templateId,
            'key' => $template->templateKey,
            'name' => $template->name,
            'channel' => $template->channel->value,
            'version' => $template->version,
            'status' => $template->status,
            'subject' => $template->subject,
            'body' => $template->body,
            'plain_text' => $template->plainText,
            'allowed_fields' => $template->allowedFields,
        ];
    }
}
