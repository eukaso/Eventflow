<?php

namespace EventFlow\Application\Idempotency;

use DateTimeImmutable;

interface IdempotencyRepository
{
    public function claim(
        IdempotencyRequest $request,
        string $leaseToken,
        DateTimeImmutable $now,
        DateTimeImmutable $leaseExpiresAt,
        DateTimeImmutable $recordExpiresAt,
    ): IdempotencyClaimResult;

    public function complete(
        int $recordId,
        string $leaseToken,
        IdempotencyResultReference $reference,
        bool $sensitiveResult,
        DateTimeImmutable $completedAt,
    ): void;

    public function fail(int $recordId, string $leaseToken, DateTimeImmutable $failedAt): void;
}
