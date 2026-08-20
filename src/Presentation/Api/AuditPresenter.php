<?php

namespace EventFlow\Presentation\Api;

use DateTimeImmutable;
use DateTimeZone;
use EventFlow\Application\Audit\{AuditEntry, AuditEntryPage, AuditEntrySummary, AuditIntegrityReport};
use EventFlow\Application\Error\RequestId;

final readonly class AuditPresenter
{
    public function page(AuditEntryPage $page, RequestId $requestId): JsonApiResponse
    {
        return new JsonApiResponse(200, [
            'data'=>array_map($this->summary(...), $page->entries),
            'meta'=>['next_after'=>$page->nextAfterAuditLogId],
            'request_id'=>$requestId->value,
        ], $this->headers($requestId));
    }

    public function resource(AuditEntry $entry, RequestId $requestId): JsonApiResponse
    {
        return new JsonApiResponse(200, ['data'=>$this->detail($entry),'request_id'=>$requestId->value], $this->headers($requestId));
    }

    public function integrity(AuditIntegrityReport $report, RequestId $requestId): JsonApiResponse
    {
        return new JsonApiResponse(200, ['data'=>[
            'valid'=>$report->valid,
            'record_count'=>$report->recordCount,
            'last_audit_log_id'=>$report->lastAuditLogId,
            'head_hash'=>$report->headHash,
            'failure_code'=>$report->failureCode,
        ],'request_id'=>$requestId->value], $this->headers($requestId));
    }

    /** @return array<string,mixed> */
    private function summary(AuditEntrySummary $entry): array
    {
        return [
            'id'=>$entry->auditLogId,
            'actor_type'=>$entry->actorType,
            'actor_user_id'=>$entry->actorUserId,
            'action'=>$entry->action->value,
            'entity_type'=>$entry->entityType->value,
            'entity_id'=>$entry->entityId,
            'summary'=>$entry->summary,
            'source'=>$entry->source->value,
            'occurred_at'=>$this->date($entry->occurredAt),
            'record_hash'=>$entry->recordHash,
        ];
    }

    /** @return array<string,mixed> */
    private function detail(AuditEntry $entry): array
    {
        $record = $entry->record;
        return [
            'id'=>$entry->auditLogId,
            'actor_type'=>$record->actorType,
            'actor_user_id'=>$record->actorUserId,
            'actor_reference'=>$record->actorReference,
            'action'=>$record->action->value,
            'entity_type'=>$record->entityType->value,
            'entity_id'=>$record->entityId,
            'operation_id'=>$record->operationId,
            'correlation_id'=>$record->correlationId,
            'summary'=>$record->summary,
            'before'=>$record->before,
            'after'=>$record->after,
            'source'=>$record->source->value,
            'reason'=>$record->reason,
            'occurred_at'=>$this->date($record->occurredAt),
            'created_at'=>$this->date($record->createdAt),
            'payload_schema_version'=>$record->payloadSchemaVersion,
            'canonicalization_version'=>$record->canonicalizationVersion,
            'previous_hash'=>$record->previousHash,
            'record_hash'=>$record->recordHash,
        ];
    }

    /** @return array<string,string> */
    private function headers(RequestId $requestId): array
    {
        return ['X-Request-ID'=>$requestId->value,'Cache-Control'=>'private, no-store, max-age=0','Pragma'=>'no-cache'];
    }

    private function date(DateTimeImmutable $date): string
    {
        return $date->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d\TH:i:s\Z');
    }
}
