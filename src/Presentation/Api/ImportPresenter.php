<?php

namespace EventFlow\Presentation\Api;

use EventFlow\Application\Error\RequestId;
use EventFlow\Application\Idempotency\IdempotencyOutcome;
use EventFlow\Application\Import\ImportDryRun;

final readonly class ImportPresenter
{
    public function validation(IdempotencyOutcome $outcome, int $eventId, int $jobId, RequestId $requestId): JsonApiResponse
    {
        $result = $outcome->response;
        $data = $result instanceof ImportDryRun
            ? [
                'import_job_id' => $jobId,
                'total_rows' => $result->totalRows,
                'ready_rows' => $result->readyRows,
                'invalid_rows' => $result->invalidRows,
                'warning_rows' => $result->warningRows,
            ]
            : ['type' => $outcome->reference->entityType, 'id' => $outcome->reference->entityId];
        return new JsonApiResponse(
            $outcome->reference->responseStatusCode,
            ['data' => $data, 'meta' => ['replayed' => $outcome->replayed], 'request_id' => $requestId->value],
            [
                'X-Request-ID' => $requestId->value,
                'Cache-Control' => 'no-store, max-age=0',
                'Location' => '/wp-json/eventflow/v1/events/' . $eventId . '/imports/' . $jobId,
            ],
        );
    }
}
