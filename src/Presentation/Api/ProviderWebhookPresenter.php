<?php

namespace EventFlow\Presentation\Api;

use EventFlow\Application\Error\RequestId;

final readonly class ProviderWebhookPresenter
{
    public function accepted(int $jobId, RequestId $requestId): JsonApiResponse
    {
        return new JsonApiResponse(
            202,
            ['data' => ['job_id' => $jobId, 'status' => 'accepted'], 'request_id' => $requestId->value],
            ['X-Request-ID' => $requestId->value, 'Cache-Control' => 'no-store, max-age=0'],
        );
    }
}
