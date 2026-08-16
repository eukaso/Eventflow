<?php

namespace EventFlow\Infrastructure\Persistence\WordPress;

use DateTimeImmutable;
use DateTimeZone;
use EventFlow\Application\Audit\AuditRecord;
use EventFlow\Application\Audit\AuditRepository;
use EventFlow\Application\Persistence\EventScope;
use EventFlow\Infrastructure\Persistence\PersistenceException;
use EventFlow\Infrastructure\Persistence\TableName;

final class WpdbAuditRepository extends AbstractWpdbRepository implements AuditRepository
{
    public function lockChainHead(?EventScope $eventScope): ?string
    {
        $table = $this->table(TableName::AUDIT_CHAIN_HEADS);
        $scopeKey = $eventScope?->eventId ?? 0;
        $row = $this->database->fetchRow(
            "SELECT head_hash FROM {$table} WHERE event_scope_key = %d FOR UPDATE",
            [$scopeKey],
        );

        if ($row === null) {
            try {
                $eventSql = $eventScope === null ? 'NULL' : '%d';
                $parameters = $eventScope === null ? [$scopeKey] : [$eventScope->eventId, $scopeKey];
                $this->database->execute(
                    "INSERT INTO {$table} (event_id, event_scope_key, canonicalization_version, updated_at) " .
                    "VALUES ({$eventSql}, %d, 1, UTC_TIMESTAMP())",
                    $parameters,
                );
            } catch (PersistenceException $exception) {
                if ($exception->safeCode !== 'database_unique_conflict') {
                    throw $exception;
                }
            }

            $row = $this->database->fetchRow(
                "SELECT head_hash FROM {$table} WHERE event_scope_key = %d FOR UPDATE",
                [$scopeKey],
            );
        }

        if ($row === null) {
            throw new PersistenceException('audit_chain_head_unavailable');
        }

        $head = $row['head_hash'] ?? null;
        if ($head === null || $head === '') {
            return null;
        }
        if (!is_string($head) || !preg_match('/^[a-f0-9]{64}$/', $head)) {
            throw new PersistenceException('audit_chain_head_invalid');
        }

        return $head;
    }

    public function append(AuditRecord $record): int
    {
        if (!preg_match('/^[a-f0-9]{64}$/', $record->recordHash)) {
            throw new PersistenceException('audit_record_hash_invalid');
        }

        $table = $this->table(TableName::AUDIT_LOGS);
        $columns = [
            'event_id', 'actor_type', 'actor_user_id', 'actor_reference', 'action_type',
            'entity_type', 'entity_id', 'operation_id', 'correlation_id', 'change_summary',
            'before_data', 'after_data', 'source_type', 'reason', 'occurred_at', 'created_at',
            'payload_schema_version', 'canonicalization_version', 'previous_hash', 'record_hash',
        ];
        $values = [];
        $parameters = [];
        $add = static function (mixed $value, string $placeholder) use (&$values, &$parameters): void {
            if ($value === null) {
                $values[] = 'NULL';
                return;
            }
            $values[] = $placeholder;
            $parameters[] = $value;
        };

        $add($record->eventScope?->eventId, '%d');
        $add($record->actorType, '%s');
        $add($record->actorUserId, '%d');
        $add($record->actorReference, '%s');
        $add($record->action->value, '%s');
        $add($record->entityType->value, '%s');
        $add($record->entityId, '%d');
        $add($record->operationId, '%s');
        $add($record->correlationId, '%s');
        $add($record->summary, '%s');
        $add($this->json($record->before), '%s');
        $add($this->json($record->after), '%s');
        $add($record->source->value, '%s');
        $add($record->reason, '%s');
        $add($this->date($record->occurredAt), '%s');
        $add($this->date($record->createdAt), '%s');
        $add($record->payloadSchemaVersion, '%d');
        $add($record->canonicalizationVersion, '%d');
        $add($record->previousHash, '%s');
        $add($record->recordHash, '%s');

        $this->database->execute(
            "INSERT INTO {$table} (" . implode(', ', $columns) . ') VALUES (' . implode(', ', $values) . ')',
            $parameters,
        );
        $auditLogId = $this->database->lastInsertId();

        $heads = $this->table(TableName::AUDIT_CHAIN_HEADS);
        $wherePrevious = $record->previousHash === null ? 'head_hash IS NULL' : 'head_hash = %s';
        $headParameters = [
            $auditLogId,
            $record->recordHash,
            $record->canonicalizationVersion,
            $this->date($record->createdAt),
            $record->eventScope?->eventId ?? 0,
        ];
        if ($record->previousHash !== null) {
            $headParameters[] = $record->previousHash;
        }
        $affected = $this->database->execute(
            "UPDATE {$heads} SET last_audit_log_id = %d, head_hash = %s, canonicalization_version = %d, " .
            "updated_at = %s WHERE event_scope_key = %d AND {$wherePrevious}",
            $headParameters,
        );
        if ($affected !== 1) {
            throw new PersistenceException('audit_chain_head_conflict');
        }

        return $auditLogId;
    }

    /** @param array<string, mixed>|null $value */
    private function json(?array $value): ?string
    {
        return $value === null ? null : json_encode(
            $value,
            JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE,
        );
    }

    private function date(DateTimeImmutable $date): string
    {
        return $date->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s');
    }
}
