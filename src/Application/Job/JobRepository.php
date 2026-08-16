<?php

namespace EventFlow\Application\Job;

use DateTimeImmutable;

interface JobRepository
{
    /** Returns the existing durable job when a logical dedupe constraint wins. */
    public function enqueue(JobRequest $request, DateTimeImmutable $createdAt): JobRecord;

    public function claimNext(
        string $leaseOwner,
        string $leaseToken,
        DateTimeImmutable $now,
        DateTimeImmutable $leaseExpiresAt,
    ): ?JobRecord;

    public function heartbeat(int $jobId, string $leaseToken, DateTimeImmutable $now, DateTimeImmutable $leaseExpiresAt): void;

    public function complete(int $jobId, string $leaseToken, DateTimeImmutable $completedAt): void;

    public function fail(
        int $jobId,
        string $leaseToken,
        string $errorCode,
        bool $deadLetter,
        DateTimeImmutable $failedAt,
        DateTimeImmutable $nextAvailableAt,
    ): void;

    public function reconcile(DateTimeImmutable $now): JobReconciliationResult;
}
