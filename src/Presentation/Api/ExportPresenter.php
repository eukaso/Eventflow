<?php

namespace EventFlow\Presentation\Api;

use DateTimeImmutable;
use DateTimeZone;
use EventFlow\Application\Error\RequestId;
use EventFlow\Application\Export\ExportDownloadGrant;
use EventFlow\Application\Export\ExportPage;
use EventFlow\Application\Export\ExportRecord;
use EventFlow\Application\Idempotency\IdempotencyOutcome;

final readonly class ExportPresenter
{
    public function page(ExportPage $page, RequestId $requestId): JsonApiResponse
    {
        return new JsonApiResponse(200, ['data'=>array_map($this->resourceData(...),$page->exports),'meta'=>['next_after'=>$page->nextAfterExportId],'request_id'=>$requestId->value], $this->headers($requestId));
    }

    public function resource(ExportRecord $export, RequestId $requestId): JsonApiResponse
    {
        return new JsonApiResponse(200, ['data'=>$this->resourceData($export),'request_id'=>$requestId->value], $this->headers($requestId));
    }

    public function creation(IdempotencyOutcome $outcome, int $eventId, RequestId $requestId): JsonApiResponse
    {
        $export = $outcome->response instanceof ExportRecord ? $outcome->response : null;
        $data = $export === null ? ['type'=>$outcome->reference->entityType,'id'=>$outcome->reference->entityId] : $this->resourceData($export);
        $headers = $this->headers($requestId);
        $headers['Location'] = '/wp-json/eventflow/v1/events/'.$eventId.'/exports/'.$outcome->reference->entityId;
        return new JsonApiResponse($outcome->reference->responseStatusCode, ['data'=>$data,'meta'=>['replayed'=>$outcome->replayed],'request_id'=>$requestId->value], $headers);
    }

    public function download(ExportDownloadGrant $grant, string $content, RequestId $requestId): BinaryApiResponse
    {
        return new BinaryApiResponse($content, [
            'X-Request-ID' => $requestId->value,
            'Content-Type' => $grant->mimeType,
            'Content-Length' => (string) $grant->sizeBytes,
            'Content-Disposition' => 'attachment; filename="'.$grant->filename.'"',
            'Digest' => 'sha-256='.base64_encode(hex2bin($grant->sha256) ?: ''),
            'Cache-Control' => 'private, no-store, max-age=0',
            'Pragma' => 'no-cache',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    /** @return array<string,mixed> */
    private function resourceData(ExportRecord $export): array
    {
        return [
            'id'=>$export->exportId,
            'type'=>$export->type->value,
            'format'=>$export->format->value,
            'contains_pii'=>$export->containsPii,
            'purpose'=>$export->purpose,
            'status'=>$export->status,
            'cutoff_at'=>$this->date($export->cutoffAt),
            'expires_at'=>$this->date($export->expiresAt),
            'artifact_sha256'=>$export->artifactHash,
            'artifact_size_bytes'=>$export->sizeBytes,
        ];
    }

    /** @return array<string,string> */
    private function headers(RequestId $requestId): array
    {
        return ['X-Request-ID'=>$requestId->value,'Cache-Control'=>'no-store, max-age=0','Pragma'=>'no-cache'];
    }

    private function date(DateTimeImmutable $date): string
    {
        return $date->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d\TH:i:s\Z');
    }
}
