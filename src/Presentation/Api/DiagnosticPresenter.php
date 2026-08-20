<?php

namespace EventFlow\Presentation\Api;

use DateTimeZone;
use EventFlow\Application\Error\RequestId;
use EventFlow\Application\Observability\DiagnosticBundle;

final readonly class DiagnosticPresenter
{
    public function bundle(DiagnosticBundle $bundle, RequestId $requestId): JsonApiResponse
    {
        return new JsonApiResponse(200, [
            'data'=>[
                'event_id'=>$bundle->eventId,
                'generated_at'=>$bundle->generatedAt->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d\TH:i:s\Z'),
                'sections'=>$bundle->sections,
            ],
            'request_id'=>$requestId->value,
        ], [
            'X-Request-ID'=>$requestId->value,
            'Cache-Control'=>'private, no-store, max-age=0',
            'Pragma'=>'no-cache',
            'X-Content-Type-Options'=>'nosniff',
        ]);
    }
}
