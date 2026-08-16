<?php

namespace EventFlow\Application\Job;

use DateInterval;
use EventFlow\Application\Clock\Clock;
use EventFlow\Application\Security\SecureRandom;
use EventFlow\Application\Transaction\TransactionManager;
use Throwable;

final readonly class JobWorker
{
    public function __construct(
        private JobRepository $repository,
        private JobHandlerRegistry $handlers,
        private TransactionManager $transactions,
        private Clock $clock,
        private SecureRandom $random,
        private WorkerSchemaGate $schemaGate,
        private JobWorkerOptions $options = new JobWorkerOptions(),
    ) {
    }

    /** Returns false when no runnable durable job exists. */
    public function runOne(string $workerId): bool
    {
        if ($workerId === '' || strlen($workerId) > 190) {
            throw new JobException('invalid_job_worker_id');
        }
        $this->schemaGate->assertCompatible();
        $leaseToken = $this->random->hex(16);
        $now = $this->clock->now();
        $job = $this->transactions->transactional(fn (): ?JobRecord => $this->repository->claimNext(
            $workerId,
            $leaseToken,
            $now,
            $now->add(new DateInterval('PT' . $this->options->leaseSeconds . 'S')),
        ));
        if ($job === null) {
            return false;
        }

        try {
            $handler = $this->handlers->require($job->jobType, $job->payloadVersion);
            $handler->validatePayload($job->payload);
            $this->transactions->assertNotActive();
            $handler->handle(new JobExecutionContext(
                $job->jobId,
                $job->eventScope,
                $job->principal(),
                $job->payload,
                $job->attemptCount,
                function () use ($job, $leaseToken): void {
                    $heartbeatAt = $this->clock->now();
                    $this->transactions->transactional(fn () => $this->repository->heartbeat(
                        $job->jobId,
                        $leaseToken,
                        $heartbeatAt,
                        $heartbeatAt->add(new DateInterval('PT' . $this->options->leaseSeconds . 'S')),
                    ));
                },
            ));
            $this->transactions->transactional(
                fn () => $this->repository->complete($job->jobId, $leaseToken, $this->clock->now()),
            );
            return true;
        } catch (Throwable $failure) {
            $retryable = !$failure instanceof JobException
                || ($failure instanceof JobExecutionException && $failure->retryable);
            $code = $failure instanceof JobException ? $failure->safeCode : 'job_execution_failed';
            $deadLetter = !$retryable || $job->attemptCount >= $job->maxAttempts;
            $failedAt = $this->clock->now();
            $delay = min(
                $this->options->maximumRetrySeconds,
                $this->options->baseRetrySeconds * (2 ** min(20, max(0, $job->attemptCount - 1))),
            );

            try {
                $this->transactions->transactional(fn () => $this->repository->fail(
                    $job->jobId,
                    $leaseToken,
                    $code,
                    $deadLetter,
                    $failedAt,
                    $failedAt->add(new DateInterval('PT' . $delay . 'S')),
                ));
            } catch (Throwable $stateFailure) {
                throw new JobException('job_state_update_failed', $failure);
            }

            return true;
        }
    }
}
