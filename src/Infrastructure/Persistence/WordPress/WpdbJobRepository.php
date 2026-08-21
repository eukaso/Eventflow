<?php

namespace EventFlow\Infrastructure\Persistence\WordPress;

use DateTimeImmutable;
use DateTimeZone;
use EventFlow\Application\Authorization\Capability;
use EventFlow\Application\Job\JobException;
use EventFlow\Application\Job\JobPayload;
use EventFlow\Application\Job\JobReconciliationResult;
use EventFlow\Application\Job\JobRecord;
use EventFlow\Application\Job\JobRepository;
use EventFlow\Application\Job\JobRequest;
use EventFlow\Application\Job\JobStatus;
use EventFlow\Application\Persistence\EventScope;
use EventFlow\Infrastructure\Persistence\PersistenceException;
use EventFlow\Infrastructure\Persistence\TableName;
use Throwable;

final class WpdbJobRepository extends AbstractWpdbRepository implements JobRepository
{
    public function enqueue(JobRequest $request, DateTimeImmutable $createdAt): JobRecord
    {
        $table = $this->table(TableName::JOBS);
        $eventSql = $request->eventScope === null ? 'NULL' : '%d';
        $dedupeSql = $request->logicalDedupeKey === null ? 'NULL' : '%s';
        $parameters = [];
        if ($request->eventScope !== null) {
            $parameters[] = $request->eventScope->eventId;
        }
        array_push(
            $parameters,
            $request->eventScope?->eventId ?? 0,
            $request->jobType,
            $request->payloadVersion,
            $this->encodeEnvelope($request),
            JobStatus::PENDING->value,
            $request->priority,
        );
        if ($request->logicalDedupeKey !== null) {
            $parameters[] = $request->logicalDedupeKey;
        }
        array_push(
            $parameters,
            $request->maxAttempts,
            $this->date($request->availableAt),
            $this->date($createdAt),
            $this->date($createdAt),
        );

        try {
            $this->database->execute(
                "INSERT INTO {$table} (event_id, event_scope_key, job_type, payload_version, payload, " .
                'job_status, priority, logical_dedupe_key, max_attempts, available_at, created_at, updated_at) ' .
                "VALUES ({$eventSql}, %d, %s, %d, %s, %s, %d, {$dedupeSql}, %d, %s, %s, %s)",
                $parameters,
            );
        } catch (PersistenceException $exception) {
            if ($exception->safeCode !== 'database_unique_conflict' || $request->logicalDedupeKey === null) {
                throw $exception;
            }
            $existing = $this->findByDedupe($request)
                ?? throw new PersistenceException('job_dedupe_race_unresolved', $exception);
            if (!$this->sameLogicalRequest($existing, $request)) {
                throw new PersistenceException('job_dedupe_conflict', $exception);
            }
            return $existing;
        }

        return new JobRecord(
            $this->database->lastInsertId(),
            $request->eventScope,
            $request->jobType,
            $request->payloadVersion,
            $request->payload,
            $request->committedCapabilities,
            JobStatus::PENDING,
            $request->priority,
            0,
            $request->maxAttempts,
        );
    }

    public function claimNext(
        string $leaseOwner,
        string $leaseToken,
        DateTimeImmutable $now,
        DateTimeImmutable $leaseExpiresAt,
    ): ?JobRecord {
        $table = $this->table(TableName::JOBS);
        $row = $this->database->fetchRow(
            'SELECT job_id, event_id, job_type, payload_version, payload, job_status, priority, ' .
            "attempt_count, max_attempts FROM {$table} WHERE attempt_count < max_attempts AND (" .
            '(job_status = %s AND available_at <= %s) OR ' .
            '(job_status = %s AND lease_expires_at IS NOT NULL AND lease_expires_at <= %s)' .
            ') ORDER BY priority ASC, available_at ASC, job_id ASC LIMIT 1 FOR UPDATE SKIP LOCKED',
            [JobStatus::PENDING->value, $this->date($now), JobStatus::RUNNING->value, $this->date($now)],
        );
        if ($row === null) {
            return null;
        }

        $jobId = (int) ($row['job_id'] ?? 0);
        if ($jobId < 1) {
            throw new PersistenceException('job_record_invalid');
        }
        $affected = $this->database->execute(
            "UPDATE {$table} SET job_status = %s, lease_token = %s, lease_owner = %s, " .
            'lease_expires_at = %s, heartbeat_at = %s, attempt_count = attempt_count + 1, ' .
            'started_at = COALESCE(started_at, %s), updated_at = %s WHERE job_id = %d',
            [
                JobStatus::RUNNING->value, $leaseToken, $leaseOwner, $this->date($leaseExpiresAt),
                $this->date($now), $this->date($now), $this->date($now), $jobId,
            ],
        );
        if ($affected !== 1) {
            throw new PersistenceException('job_claim_failed');
        }
        $row['job_status'] = JobStatus::RUNNING->value;
        $row['attempt_count'] = (int) ($row['attempt_count'] ?? 0) + 1;
        return $this->map($row);
    }

    public function heartbeat(int $jobId, string $leaseToken, DateTimeImmutable $now, DateTimeImmutable $leaseExpiresAt): void
    {
        $table = $this->table(TableName::JOBS);
        $affected = $this->database->execute(
            "UPDATE {$table} SET heartbeat_at = %s, lease_expires_at = %s, updated_at = %s " .
            'WHERE job_id = %d AND job_status = %s AND lease_token = %s',
            [$this->date($now), $this->date($leaseExpiresAt), $this->date($now), $jobId, JobStatus::RUNNING->value, $leaseToken],
        );
        if ($affected === 1) return;
        $stillOwned = (int) $this->database->fetchValue(
            "SELECT EXISTS(SELECT 1 FROM {$table} WHERE job_id = %d AND job_status = %s " .
            'AND lease_token = %s AND lease_expires_at > %s)',
            [$jobId, JobStatus::RUNNING->value, $leaseToken, $this->date($now)],
        );
        if ($stillOwned !== 1) throw new PersistenceException('job_lease_lost');
    }

    public function complete(int $jobId, string $leaseToken, DateTimeImmutable $completedAt): void
    {
        $this->leaseUpdate(
            'job_status = %s, lease_token = NULL, lease_owner = NULL, lease_expires_at = NULL, ' .
            'heartbeat_at = NULL, completed_at = %s, last_error_code = NULL, updated_at = %s',
            [JobStatus::COMPLETED->value, $this->date($completedAt), $this->date($completedAt)],
            $jobId,
            $leaseToken,
            'job_lease_lost',
        );
    }

    public function fail(
        int $jobId,
        string $leaseToken,
        string $errorCode,
        bool $deadLetter,
        DateTimeImmutable $failedAt,
        DateTimeImmutable $nextAvailableAt,
    ): void {
        if (!preg_match('/^[a-z][a-z0-9_.-]{2,189}$/', $errorCode)) {
            $errorCode = 'job_execution_failed';
        }
        if ($deadLetter) {
            $set = 'job_status = %s, lease_token = NULL, lease_owner = NULL, lease_expires_at = NULL, ' .
                'heartbeat_at = NULL, failed_at = %s, dead_lettered_at = %s, last_error_code = %s, updated_at = %s';
            $parameters = [
                JobStatus::DEAD_LETTER->value, $this->date($failedAt), $this->date($failedAt),
                $errorCode, $this->date($failedAt),
            ];
        } else {
            $set = 'job_status = %s, lease_token = NULL, lease_owner = NULL, lease_expires_at = NULL, ' .
                'heartbeat_at = NULL, failed_at = %s, available_at = %s, last_error_code = %s, updated_at = %s';
            $parameters = [
                JobStatus::PENDING->value, $this->date($failedAt), $this->date($nextAvailableAt),
                $errorCode, $this->date($failedAt),
            ];
        }
        $this->leaseUpdate($set, $parameters, $jobId, $leaseToken, 'job_lease_lost');
    }

    public function reconcile(DateTimeImmutable $now): JobReconciliationResult
    {
        $table = $this->table(TableName::JOBS);
        $dead = $this->database->execute(
            "UPDATE {$table} SET job_status = %s, lease_token = NULL, lease_owner = NULL, " .
            'lease_expires_at = NULL, heartbeat_at = NULL, failed_at = %s, dead_lettered_at = %s, ' .
            'last_error_code = %s, updated_at = %s WHERE attempt_count >= max_attempts AND ' .
            '(job_status = %s OR (job_status = %s AND (lease_expires_at IS NULL OR lease_expires_at <= %s)))',
            [
                JobStatus::DEAD_LETTER->value, $this->date($now), $this->date($now),
                'job_attempts_exhausted', $this->date($now), JobStatus::PENDING->value,
                JobStatus::RUNNING->value, $this->date($now),
            ],
        );
        $recovered = $this->database->execute(
            "UPDATE {$table} SET job_status = %s, lease_token = NULL, lease_owner = NULL, " .
            'lease_expires_at = NULL, heartbeat_at = NULL, available_at = %s, last_error_code = %s, ' .
            'updated_at = %s WHERE job_status = %s AND (lease_expires_at IS NULL OR lease_expires_at <= %s) ' .
            'AND attempt_count < max_attempts',
            [
                JobStatus::PENDING->value, $this->date($now), 'job_lease_expired', $this->date($now),
                JobStatus::RUNNING->value, $this->date($now),
            ],
        );
        $runnable = (int) $this->database->fetchValue(
            "SELECT EXISTS(SELECT 1 FROM {$table} WHERE job_status = %s AND available_at <= %s " .
            'AND attempt_count < max_attempts)',
            [JobStatus::PENDING->value, $this->date($now)],
        ) === 1;
        return new JobReconciliationResult($recovered, $dead, $runnable);
    }

    private function findByDedupe(JobRequest $request): ?JobRecord
    {
        $table = $this->table(TableName::JOBS);
        $row = $this->database->fetchRow(
            'SELECT job_id, event_id, job_type, payload_version, payload, job_status, priority, ' .
            "attempt_count, max_attempts FROM {$table} WHERE event_scope_key = %d AND job_type = %s " .
            'AND logical_dedupe_key = %s',
            [$request->eventScope?->eventId ?? 0, $request->jobType, $request->logicalDedupeKey],
        );
        return $row === null ? null : $this->map($row);
    }

    private function sameLogicalRequest(JobRecord $existing, JobRequest $request): bool
    {
        $existingCapabilities = array_map(
            static fn (Capability $capability): string => $capability->value,
            $existing->committedCapabilities,
        );
        $requestedCapabilities = array_map(
            static fn (Capability $capability): string => $capability->value,
            $request->committedCapabilities,
        );
        sort($existingCapabilities, SORT_STRING);
        sort($requestedCapabilities, SORT_STRING);

        return $existing->payloadVersion === $request->payloadVersion
            && $this->normalize($existing->payload) === $this->normalize($request->payload)
            && $existingCapabilities === $requestedCapabilities
            && $existing->priority === $request->priority
            && $existing->maxAttempts === $request->maxAttempts;
    }

    /** @param list<int|string> $parameters */
    private function leaseUpdate(
        string $set,
        array $parameters,
        int $jobId,
        string $leaseToken,
        string $failureCode,
    ): void {
        $table = $this->table(TableName::JOBS);
        array_push($parameters, $jobId, JobStatus::RUNNING->value, $leaseToken);
        $affected = $this->database->execute(
            "UPDATE {$table} SET {$set} WHERE job_id = %d AND job_status = %s AND lease_token = %s",
            $parameters,
        );
        if ($affected !== 1) {
            throw new PersistenceException($failureCode);
        }
    }

    private function encodeEnvelope(JobRequest $request): string
    {
        return json_encode([
            'data' => $request->payload,
            'committed_capabilities' => array_map(
                static fn (Capability $capability): string => $capability->value,
                $request->committedCapabilities,
            ),
        ], JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }

    /** @param array<string, mixed> $row */
    private function map(array $row): JobRecord
    {
        try {
            $jobId = (int) ($row['job_id'] ?? 0);
            $eventId = $row['event_id'] ?? null;
            $type = (string) ($row['job_type'] ?? '');
            $version = (int) ($row['payload_version'] ?? 0);
            $decoded = json_decode((string) ($row['payload'] ?? ''), true, 17, JSON_THROW_ON_ERROR);
            if (!is_array($decoded) || !isset($decoded['data'], $decoded['committed_capabilities'])) {
                throw new JobException('job_payload_envelope_invalid');
            }
            $payload = $decoded['data'];
            $capabilityValues = $decoded['committed_capabilities'];
            if (!is_array($payload) || !is_array($capabilityValues)) {
                throw new JobException('job_payload_envelope_invalid');
            }
            JobPayload::validate($payload);
            $capabilities = [];
            $seenCapabilities = [];
            foreach ($capabilityValues as $value) {
                if (!is_string($value) || ($capability = Capability::tryFrom($value)) === null) {
                    throw new JobException('job_committed_authority_invalid');
                }
                if (isset($seenCapabilities[$capability->value])) {
                    throw new JobException('job_committed_authority_invalid');
                }
                $seenCapabilities[$capability->value] = true;
                $capabilities[] = $capability;
            }
            $scope = $eventId === null ? null : new EventScope((int) $eventId);
            $status = JobStatus::tryFrom((string) ($row['job_status'] ?? ''));
            $priority = (int) ($row['priority'] ?? -1);
            $attemptCount = (int) ($row['attempt_count'] ?? -1);
            $maxAttempts = (int) ($row['max_attempts'] ?? 0);
            if (
                $jobId < 1 || $status === null
                || !preg_match('/^[a-z][a-z0-9_.-]{2,99}$/', $type)
                || $version < 1 || $version > 65535
                || $priority < 0 || $priority > 65535
                || $attemptCount < 0 || $attemptCount > 65535
                || $maxAttempts < 1 || $maxAttempts > 100
                || $attemptCount > $maxAttempts
                || ($scope === null && $capabilities !== [])
            ) {
                throw new JobException('job_record_invalid');
            }
            return new JobRecord(
                $jobId, $scope, $type, $version, $payload, $capabilities, $status,
                $priority, $attemptCount, $maxAttempts,
            );
        } catch (Throwable $exception) {
            if ($exception instanceof PersistenceException) {
                throw $exception;
            }
            throw new PersistenceException('job_record_invalid', $exception);
        }
    }

    private function date(DateTimeImmutable $date): string
    {
        return $date->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s');
    }

    private function normalize(mixed $value): mixed
    {
        if (!is_array($value)) {
            return $value;
        }
        if (!array_is_list($value)) {
            ksort($value, SORT_STRING);
        }
        foreach ($value as $key => $item) {
            $value[$key] = $this->normalize($item);
        }
        return $value;
    }
}
