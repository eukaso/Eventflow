<?php

namespace EventFlow\Infrastructure\Persistence\WordPress;

use DateTimeImmutable;
use DateTimeZone;
use EventFlow\Application\Idempotency\IdempotencyClaimResult;
use EventFlow\Application\Idempotency\IdempotencyClaimState;
use EventFlow\Application\Idempotency\IdempotencyRecord;
use EventFlow\Application\Idempotency\IdempotencyRepository;
use EventFlow\Application\Idempotency\IdempotencyRequest;
use EventFlow\Application\Idempotency\IdempotencyResultReference;
use EventFlow\Infrastructure\Persistence\PersistenceException;
use EventFlow\Infrastructure\Persistence\TableName;
use InvalidArgumentException;

final class WpdbIdempotencyRepository extends AbstractWpdbRepository implements IdempotencyRepository
{
    public function claim(
        IdempotencyRequest $request,
        string $leaseToken,
        DateTimeImmutable $now,
        DateTimeImmutable $leaseExpiresAt,
        DateTimeImmutable $recordExpiresAt,
    ): IdempotencyClaimResult {
        $record = $this->findForUpdate($request);

        if ($record === null) {
            try {
                return $this->insertClaim($request, $leaseToken, $now, $leaseExpiresAt, $recordExpiresAt);
            } catch (PersistenceException $exception) {
                if ($exception->safeCode !== 'database_unique_conflict') {
                    throw $exception;
                }

                $record = $this->findForUpdate($request);

                if ($record === null) {
                    throw new PersistenceException('idempotency_claim_race_unresolved', $exception);
                }
            }
        }

        if ($record['expiresAt'] <= $now) {
            return $this->reinitializeExpired(
                $record['record'],
                $request,
                $leaseToken,
                $now,
                $leaseExpiresAt,
                $recordExpiresAt,
            );
        }

        if (!hash_equals($record['record']->requestFingerprint, $request->requestFingerprint)) {
            return new IdempotencyClaimResult(IdempotencyClaimState::CONFLICT, $record['record']);
        }

        if ($record['record']->executionStatus === 'completed') {
            return new IdempotencyClaimResult(IdempotencyClaimState::REPLAY, $record['record']);
        }

        if (
            $record['record']->executionStatus === 'in_progress'
            && $record['record']->leaseExpiresAt !== null
            && $record['record']->leaseExpiresAt > $now
        ) {
            return new IdempotencyClaimResult(IdempotencyClaimState::IN_PROGRESS, $record['record']);
        }

        return $this->reacquire($record['record'], $leaseToken, $now, $leaseExpiresAt, $recordExpiresAt);
    }

    public function complete(
        int $recordId,
        string $leaseToken,
        IdempotencyResultReference $reference,
        bool $sensitiveResult,
        DateTimeImmutable $completedAt,
    ): void {
        $table = $this->table(TableName::IDEMPOTENCY_RECORDS);
        $affected = $this->database->execute(
            "UPDATE {$table} SET execution_status = %s, execution_lease_token = NULL, " .
            'execution_lease_expires_at = NULL, result_entity_type = %s, result_entity_id = %d, ' .
            'response_status_code = %d, sensitive_result = %d, completed_at = %s, failed_at = NULL, updated_at = %s ' .
            'WHERE idempotency_record_id = %d AND execution_status = %s AND execution_lease_token = %s',
            [
                'completed',
                $reference->entityType,
                $reference->entityId,
                $reference->responseStatusCode,
                $sensitiveResult ? 1 : 0,
                $this->formatDate($completedAt),
                $this->formatDate($completedAt),
                $recordId,
                'in_progress',
                $leaseToken,
            ],
        );

        if ($affected !== 1) {
            throw new PersistenceException('idempotency_lease_lost');
        }
    }

    public function fail(int $recordId, string $leaseToken, DateTimeImmutable $failedAt): void
    {
        $table = $this->table(TableName::IDEMPOTENCY_RECORDS);
        $affected = $this->database->execute(
            "UPDATE {$table} SET execution_status = %s, execution_lease_token = NULL, " .
            'execution_lease_expires_at = NULL, failed_at = %s, updated_at = %s ' .
            'WHERE idempotency_record_id = %d AND execution_status = %s AND execution_lease_token = %s',
            [
                'failed',
                $this->formatDate($failedAt),
                $this->formatDate($failedAt),
                $recordId,
                'in_progress',
                $leaseToken,
            ],
        );

        if ($affected !== 1) {
            throw new PersistenceException('idempotency_lease_lost');
        }
    }

    /**
     * @return array{record: IdempotencyRecord, expiresAt: DateTimeImmutable}|null
     */
    private function findForUpdate(IdempotencyRequest $request): ?array
    {
        $table = $this->table(TableName::IDEMPOTENCY_RECORDS);
        $row = $this->database->fetchRow(
            'SELECT idempotency_record_id, request_fingerprint, execution_status, execution_lease_expires_at, ' .
            'result_entity_type, result_entity_id, response_status_code, sensitive_result, expires_at ' .
            "FROM {$table} WHERE event_scope_key = %d AND principal_scope = %s " .
            'AND operation_name = %s AND idempotency_key_digest = %s FOR UPDATE',
            [
                $request->eventScopeKey,
                $request->principalScope,
                $request->operationName,
                $request->keyDigest,
            ],
        );

        if ($row === null) {
            return null;
        }

        return [
            'record' => $this->mapRecord($row),
            'expiresAt' => $this->parseRequiredDate($row['expires_at'] ?? null),
        ];
    }

    private function insertClaim(
        IdempotencyRequest $request,
        string $leaseToken,
        DateTimeImmutable $now,
        DateTimeImmutable $leaseExpiresAt,
        DateTimeImmutable $recordExpiresAt,
    ): IdempotencyClaimResult {
        $table = $this->table(TableName::IDEMPOTENCY_RECORDS);
        $eventIdSql = $request->eventScope === null ? 'NULL' : '%d';
        $parameters = [];

        if ($request->eventScope !== null) {
            $parameters[] = $request->eventScope->eventId;
        }

        array_push(
            $parameters,
            $request->eventScopeKey,
            $request->principalScope,
            $request->operationName,
            $request->keyDigest,
            $request->requestFingerprint,
            'in_progress',
            $leaseToken,
            $this->formatDate($leaseExpiresAt),
            $this->formatDate($recordExpiresAt),
            $this->formatDate($now),
            $this->formatDate($now),
        );

        $this->database->execute(
            "INSERT INTO {$table} (event_id, event_scope_key, principal_scope, operation_name, " .
            'idempotency_key_digest, request_fingerprint, execution_status, execution_lease_token, ' .
            'execution_lease_expires_at, sensitive_result, expires_at, created_at, updated_at) ' .
            "VALUES ({$eventIdSql}, %d, %s, %s, %s, %s, %s, %s, %s, 0, %s, %s, %s)",
            $parameters,
        );

        $record = new IdempotencyRecord(
            $this->database->lastInsertId(),
            $request->requestFingerprint,
            'in_progress',
            $leaseExpiresAt,
            null,
            false,
        );

        return new IdempotencyClaimResult(IdempotencyClaimState::ACQUIRED, $record);
    }

    private function reacquire(
        IdempotencyRecord $record,
        string $leaseToken,
        DateTimeImmutable $now,
        DateTimeImmutable $leaseExpiresAt,
        DateTimeImmutable $recordExpiresAt,
    ): IdempotencyClaimResult {
        $table = $this->table(TableName::IDEMPOTENCY_RECORDS);
        $this->database->execute(
            "UPDATE {$table} SET execution_status = %s, execution_lease_token = %s, " .
            'execution_lease_expires_at = %s, result_entity_type = NULL, result_entity_id = NULL, ' .
            'response_status_code = NULL, sensitive_result = 0, completed_at = NULL, failed_at = NULL, ' .
            'expires_at = %s, updated_at = %s ' .
            'WHERE idempotency_record_id = %d',
            [
                'in_progress',
                $leaseToken,
                $this->formatDate($leaseExpiresAt),
                $this->formatDate($recordExpiresAt),
                $this->formatDate($now),
                $record->recordId,
            ],
        );

        return new IdempotencyClaimResult(
            IdempotencyClaimState::ACQUIRED,
            new IdempotencyRecord(
                $record->recordId,
                $record->requestFingerprint,
                'in_progress',
                $leaseExpiresAt,
                null,
                false,
            ),
        );
    }

    private function reinitializeExpired(
        IdempotencyRecord $record,
        IdempotencyRequest $request,
        string $leaseToken,
        DateTimeImmutable $now,
        DateTimeImmutable $leaseExpiresAt,
        DateTimeImmutable $recordExpiresAt,
    ): IdempotencyClaimResult {
        $table = $this->table(TableName::IDEMPOTENCY_RECORDS);
        $this->database->execute(
            "UPDATE {$table} SET request_fingerprint = %s, execution_status = %s, execution_lease_token = %s, " .
            'execution_lease_expires_at = %s, result_entity_type = NULL, result_entity_id = NULL, ' .
            'response_status_code = NULL, sensitive_result = 0, completed_at = NULL, failed_at = NULL, ' .
            'expires_at = %s, updated_at = %s WHERE idempotency_record_id = %d',
            [
                $request->requestFingerprint,
                'in_progress',
                $leaseToken,
                $this->formatDate($leaseExpiresAt),
                $this->formatDate($recordExpiresAt),
                $this->formatDate($now),
                $record->recordId,
            ],
        );

        return new IdempotencyClaimResult(
            IdempotencyClaimState::ACQUIRED,
            new IdempotencyRecord(
                $record->recordId,
                $request->requestFingerprint,
                'in_progress',
                $leaseExpiresAt,
                null,
                false,
            ),
        );
    }

    /** @param array<string, mixed> $row */
    private function mapRecord(array $row): IdempotencyRecord
    {
        $recordId = (int) ($row['idempotency_record_id'] ?? 0);
        $fingerprint = (string) ($row['request_fingerprint'] ?? '');
        $status = (string) ($row['execution_status'] ?? '');

        if (
            $recordId < 1
            || !preg_match('/^[a-f0-9]{64}$/', $fingerprint)
            || !in_array($status, ['in_progress', 'completed', 'failed'], true)
        ) {
            throw new PersistenceException('idempotency_record_invalid');
        }

        try {
            $resultReference = null;

            if (
                isset($row['result_entity_type'], $row['result_entity_id'], $row['response_status_code'])
                && $row['result_entity_type'] !== ''
            ) {
                $resultReference = new IdempotencyResultReference(
                    (string) $row['result_entity_type'],
                    (int) $row['result_entity_id'],
                    (int) $row['response_status_code'],
                );
            }

            return new IdempotencyRecord(
                $recordId,
                $fingerprint,
                $status,
                $this->parseNullableDate($row['execution_lease_expires_at'] ?? null),
                $resultReference,
                (int) ($row['sensitive_result'] ?? 0) === 1,
            );
        } catch (InvalidArgumentException $exception) {
            throw new PersistenceException('idempotency_record_invalid', $exception);
        }
    }

    private function formatDate(DateTimeImmutable $date): string
    {
        return $date->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s');
    }

    private function parseRequiredDate(mixed $value): DateTimeImmutable
    {
        return $this->parseNullableDate($value)
            ?? throw new PersistenceException('idempotency_date_invalid');
    }

    private function parseNullableDate(mixed $value): ?DateTimeImmutable
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (!is_string($value)) {
            throw new PersistenceException('idempotency_date_invalid');
        }

        $date = DateTimeImmutable::createFromFormat('!Y-m-d H:i:s', $value, new DateTimeZone('UTC'));

        if ($date === false) {
            throw new PersistenceException('idempotency_date_invalid');
        }

        return $date;
    }
}
