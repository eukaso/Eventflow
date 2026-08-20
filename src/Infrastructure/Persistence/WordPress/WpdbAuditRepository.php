<?php

namespace EventFlow\Infrastructure\Persistence\WordPress;

use DateTimeImmutable;
use DateTimeZone;
use EventFlow\Application\Audit\AuditAccessRepository;
use EventFlow\Application\Audit\AuditAction;
use EventFlow\Application\Audit\AuditChainSnapshot;
use EventFlow\Application\Audit\AuditEntityType;
use EventFlow\Application\Audit\AuditEntry;
use EventFlow\Application\Audit\AuditEntryPage;
use EventFlow\Application\Audit\AuditEntrySummary;
use EventFlow\Application\Audit\AuditException;
use EventFlow\Application\Audit\AuditRecord;
use EventFlow\Application\Audit\AuditRepository;
use EventFlow\Application\Audit\AuditSource;
use EventFlow\Application\Persistence\EventScope;
use EventFlow\Infrastructure\Persistence\PersistenceException;
use EventFlow\Infrastructure\Persistence\TableName;

final class WpdbAuditRepository extends AbstractWpdbRepository implements AuditRepository, AuditAccessRepository
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

    public function listEntries(
        EventScope $scope,
        int $limit,
        ?int $afterAuditLogId,
        ?AuditAction $action,
        ?AuditEntityType $entityType,
        ?int $entityId,
        ?int $actorUserId,
        ?AuditSource $source,
        ?DateTimeImmutable $occurredFrom,
        ?DateTimeImmutable $occurredUntil,
    ): AuditEntryPage {
        $table = $this->table(TableName::AUDIT_LOGS);
        $where = '';
        $parameters = [$scope->eventId];
        $filters = [
            [$afterAuditLogId, 'audit_log_id > %d'],
            [$action?->value, 'action_type = %s'],
            [$entityType?->value, 'entity_type = %s'],
            [$entityId, 'entity_id = %d'],
            [$actorUserId, 'actor_user_id = %d'],
            [$source?->value, 'source_type = %s'],
            [$occurredFrom === null ? null : $this->date($occurredFrom), 'occurred_at >= %s'],
            [$occurredUntil === null ? null : $this->date($occurredUntil), 'occurred_at <= %s'],
        ];
        foreach ($filters as [$value, $condition]) {
            if ($value !== null) {
                $where .= " AND {$condition}";
                $parameters[] = $value;
            }
        }
        $parameters[] = $limit + 1;
        $rows = $this->database->fetchAll(
            "SELECT audit_log_id,event_id,actor_type,actor_user_id,action_type,entity_type,entity_id," .
            "change_summary,source_type,occurred_at,record_hash FROM {$table} " .
            "WHERE event_id = %d{$where} ORDER BY audit_log_id ASC LIMIT %d",
            $parameters,
        );
        $more = count($rows) > $limit;
        if ($more) array_pop($rows);
        $entries = array_map(fn (array $row): AuditEntrySummary => $this->hydrateSummary($row, $scope), $rows);

        return new AuditEntryPage(
            $entries,
            $more && $entries !== [] ? $entries[array_key_last($entries)]->auditLogId : null,
        );
    }

    public function findEntry(EventScope $scope, int $auditLogId): ?AuditEntry
    {
        $table = $this->table(TableName::AUDIT_LOGS);
        $row = $this->database->fetchRow(
            "SELECT * FROM {$table} WHERE event_id = %d AND audit_log_id = %d LIMIT 1",
            [$scope->eventId, $auditLogId],
        );

        return $row === null ? null : new AuditEntry($auditLogId, $this->hydrateRecord($row, $scope));
    }

    public function chainSnapshot(EventScope $scope, int $maximumRecords): AuditChainSnapshot
    {
        $heads = $this->table(TableName::AUDIT_CHAIN_HEADS);
        $head = $this->database->fetchRow(
            "SELECT last_audit_log_id,head_hash FROM {$heads} WHERE event_scope_key = %d LIMIT 1",
            [$scope->eventId],
        );
        if ($head === null) {
            return new AuditChainSnapshot([], null, null);
        }
        $headHash = is_string($head['head_hash']) ? $head['head_hash'] : null;
        if ($head['last_audit_log_id'] === null) {
            return new AuditChainSnapshot([], null, $headHash);
        }

        $lastId = (int) $head['last_audit_log_id'];
        $table = $this->table(TableName::AUDIT_LOGS);
        $rows = $this->database->fetchAll(
            "SELECT * FROM {$table} WHERE event_id = %d AND audit_log_id <= %d " .
            "ORDER BY audit_log_id ASC LIMIT %d",
            [$scope->eventId, $lastId, $maximumRecords + 1],
        );
        if (count($rows) > $maximumRecords) {
            throw new AuditException('audit_chain_too_large');
        }

        return new AuditChainSnapshot(
            array_map(fn (array $row): AuditRecord => $this->hydrateRecord($row, $scope), $rows),
            $lastId,
            $headHash,
        );
    }

    /** @param array<string, mixed> $row */
    private function hydrateSummary(array $row, EventScope $scope): AuditEntrySummary
    {
        return new AuditEntrySummary(
            (int) $row['audit_log_id'], $scope, (string) $row['actor_type'],
            $row['actor_user_id'] === null ? null : (int) $row['actor_user_id'],
            AuditAction::from((string) $row['action_type']),
            AuditEntityType::from((string) $row['entity_type']),
            $row['entity_id'] === null ? null : (int) $row['entity_id'],
            $row['change_summary'] === null ? null : (string) $row['change_summary'],
            AuditSource::from((string) $row['source_type']),
            $this->timestamp((string) $row['occurred_at']),
            (string) $row['record_hash'],
        );
    }

    /** @param array<string, mixed> $row */
    private function hydrateRecord(array $row, EventScope $scope): AuditRecord
    {
        return new AuditRecord(
            $scope, (string) $row['actor_type'],
            $row['actor_user_id'] === null ? null : (int) $row['actor_user_id'],
            $row['actor_reference'] === null ? null : (string) $row['actor_reference'],
            AuditAction::from((string) $row['action_type']),
            AuditEntityType::from((string) $row['entity_type']),
            $row['entity_id'] === null ? null : (int) $row['entity_id'],
            $row['operation_id'] === null ? null : (string) $row['operation_id'],
            $row['correlation_id'] === null ? null : (string) $row['correlation_id'],
            $row['change_summary'] === null ? null : (string) $row['change_summary'],
            $this->decode($row['before_data']), $this->decode($row['after_data']),
            AuditSource::from((string) $row['source_type']),
            $row['reason'] === null ? null : (string) $row['reason'],
            $this->timestamp((string) $row['occurred_at']), $this->timestamp((string) $row['created_at']),
            (int) $row['payload_schema_version'], (int) $row['canonicalization_version'],
            $row['previous_hash'] === null ? null : (string) $row['previous_hash'],
            (string) $row['record_hash'],
        );
    }

    /** @return array<string, mixed>|null */
    private function decode(mixed $value): ?array
    {
        if ($value === null) return null;
        $decoded = json_decode((string) $value, true, 512, JSON_THROW_ON_ERROR);
        if (!is_array($decoded)) throw new AuditException('audit_payload_invalid');
        return $decoded;
    }

    private function timestamp(string $value): DateTimeImmutable
    {
        return new DateTimeImmutable($value, new DateTimeZone('UTC'));
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
