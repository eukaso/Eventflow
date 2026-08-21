<?php

namespace EventFlow\Infrastructure\Deployment;

use DateTimeImmutable;
use EventFlow\Application\Authorization\AuthorizationException;
use EventFlow\Application\Authorization\PrincipalContext;
use EventFlow\Application\Clock\Clock;
use EventFlow\Application\Deployment\OperationsCertificationSnapshot;
use EventFlow\Application\Error\RequestId;
use EventFlow\Application\Job\JobRequest;
use EventFlow\Application\Persistence\EventScope;
use EventFlow\Application\Security\SecureRandom;
use EventFlow\Bootstrap\DatabaseFoundation;
use EventFlow\Infrastructure\Persistence\TableName;
use RuntimeException;

final readonly class WordPressOperationsCertificationProbe
{
    public function __construct(
        private DatabaseFoundation $foundation,
        private Clock $clock,
        private SecureRandom $random,
    ) {}

    public function capture(EventScope $scope, PrincipalContext $principal): OperationsCertificationSnapshot
    {
        $cron = $this->cron();
        $worker = $this->worker();
        $storage = $this->storage();
        $anonymousDenied = false;
        try {
            $this->foundation->exportAccess->list(PrincipalContext::anonymous(), $scope, 1, containsPii: false);
        } catch (AuthorizationException) {
            $anonymousDenied = true;
        }
        $audit = $this->foundation->auditAccess->verifyIntegrity($principal, $scope);
        $diagnostics = $this->foundation->diagnostics->export(
            $principal,
            $scope,
            new RequestId('operations_' . $this->random->hex(16)),
        );
        $encoded = json_encode($diagnostics->sections, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES);
        $diagnosticsSafe = preg_match('/\bBearer\s+|[A-Z0-9._%+-]+@[A-Z0-9.-]+\.[A-Z]{2,}/i', $encoded) !== 1
            && !str_contains(strtolower($encoded), 'authorization')
            && !str_contains(strtolower($encoded), 'private_key');

        return new OperationsCertificationSnapshot(
            cronScheduled: $cron['scheduled'],
            cronCadenceSeconds: $cron['cadence'],
            workerCompleted: $worker['completed'],
            heartbeatRecorded: $worker['heartbeat'],
            retryScheduled: $worker['retry_scheduled'],
            retryCompleted: $worker['retry_completed'],
            leaseRecovered: $worker['lease_recovered'],
            protectedStorageRoundTrip: $storage,
            anonymousExportDenied: $anonymousDenied,
            auditIntegrity: $audit->valid,
            auditRecords: $audit->recordCount,
            privacyReconciled: $this->foundation->privacy->isReconciled(),
            diagnosticsSanitized: $diagnosticsSafe,
            diagnosticSections: count($diagnostics->sections),
        );
    }

    /** @return array{scheduled:bool,cadence:int} */
    private function cron(): array
    {
        if (!function_exists('wp_get_scheduled_event') || !function_exists('wp_get_schedules')) return ['scheduled' => false, 'cadence' => 0];
        $event = wp_get_scheduled_event('eventflow_worker_tick');
        $schedules = wp_get_schedules();
        $cadence = is_object($event) && isset($event->schedule, $schedules[$event->schedule]['interval']) ? (int) $schedules[$event->schedule]['interval'] : 0;
        return ['scheduled' => is_object($event), 'cadence' => $cadence];
    }

    /** @return array{completed:bool,heartbeat:bool,retry_scheduled:bool,retry_completed:bool,lease_recovered:bool} */
    private function worker(): array
    {
        $jobs = $this->foundation->jobs;
        $now = $this->clock->now();
        $suffix = $this->random->hex(16);
        $heartbeat = $jobs->enqueue(JobRequest::create(null, 'operations.probe', 1, ['mode' => 'heartbeat'], [], $now, 0, 3, 'operations-heartbeat-' . $suffix), $now);
        $this->foundation->jobWorker->runOne('operations-certification');
        $heartbeatRow = $this->job($heartbeat->jobId);
        $completed = ($heartbeatRow['job_status'] ?? null) === 'completed' && (int) ($heartbeatRow['attempt_count'] ?? 0) === 1;

        $retry = $jobs->enqueue(JobRequest::create(null, 'operations.probe', 1, ['mode' => 'retry_once'], [], $this->clock->now(), 0, 3, 'operations-retry-' . $suffix), $this->clock->now());
        $this->foundation->jobWorker->runOne('operations-certification');
        $retryRow = $this->job($retry->jobId);
        $failedAt = new DateTimeImmutable((string) ($retryRow['failed_at'] ?? ''));
        $availableAt = new DateTimeImmutable((string) ($retryRow['available_at'] ?? ''));
        $retryScheduled = ($retryRow['job_status'] ?? null) === 'pending'
            && ($retryRow['last_error_code'] ?? null) === 'operations_probe_retry'
            && $availableAt->getTimestamp() - $failedAt->getTimestamp() >= 30;
        if ($retryScheduled) sleep(31);
        $this->foundation->jobWorker->runOne('operations-certification');
        $retryRow = $this->job($retry->jobId);
        $retryCompleted = ($retryRow['job_status'] ?? null) === 'completed' && (int) ($retryRow['attempt_count'] ?? 0) === 2;

        $lease = $jobs->enqueue(JobRequest::create(null, 'operations.probe', 1, ['mode' => 'heartbeat'], [], $this->clock->now(), 0, 3, 'operations-lease-' . $suffix), $this->clock->now());
        $claimed = $jobs->claimNext('operations-stale', $this->random->hex(16), $this->clock->now(), $this->clock->now()->modify('-1 second'));
        if ($claimed === null || $claimed->jobId !== $lease->jobId) throw new RuntimeException('operations_probe_claim_mismatch');
        $reconciliation = $jobs->reconcile($this->clock->now());
        $this->foundation->jobWorker->runOne('operations-certification');
        $leaseRow = $this->job($lease->jobId);

        return [
            'completed' => $completed,
            'heartbeat' => $completed,
            'retry_scheduled' => $retryScheduled,
            'retry_completed' => $retryCompleted,
            'lease_recovered' => $reconciliation->recovered >= 1 && ($leaseRow['job_status'] ?? null) === 'completed' && (int) ($leaseRow['attempt_count'] ?? 0) === 2,
        ];
    }

    /** @return array<string,mixed> */
    private function job(int $jobId): array
    {
        $table = $this->foundation->tableNames->get(TableName::JOBS);
        return $this->foundation->database->fetchRow(
            "SELECT job_status,attempt_count,available_at,failed_at,last_error_code FROM {$table} WHERE job_id=%d LIMIT 1",
            [$jobId],
        ) ?? throw new RuntimeException('operations_probe_job_missing');
    }

    private function storage(): bool
    {
        if (!defined('EVENTFLOW_PROTECTED_EXPORT_DIR')) return false;
        $base = realpath((string) EVENTFLOW_PROTECTED_EXPORT_DIR);
        $web = defined('ABSPATH') ? realpath((string) ABSPATH) : false;
        if ($base === false || $web === false || is_link($base) || !is_writable($base) || str_starts_with($base, rtrim($web, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR)) return false;
        $path = $base . DIRECTORY_SEPARATOR . '.eventflow-operations-' . $this->random->hex(16) . '.tmp';
        $payload = 'eventflow-operations-certification';
        try {
            if (file_put_contents($path, $payload, LOCK_EX) !== strlen($payload)) return false;
            chmod($path, 0600);
            return !is_link($path) && hash_file('sha256', $path) === hash('sha256', $payload) && file_get_contents($path) === $payload;
        } finally {
            if (is_file($path)) unlink($path);
        }
    }
}
