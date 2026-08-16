<?php

namespace EventFlow\Application\Idempotency;

use DateTimeImmutable;
use InvalidArgumentException;

final readonly class IdempotencyRecord
{
    public function __construct(
        public int $recordId,
        public string $requestFingerprint,
        public string $executionStatus,
        public ?DateTimeImmutable $leaseExpiresAt,
        public ?IdempotencyResultReference $resultReference,
        public bool $sensitiveResult,
    ) {
        if (
            $recordId < 1
            || !preg_match('/^[a-f0-9]{64}$/', $requestFingerprint)
            || !in_array($executionStatus, ['in_progress', 'completed', 'failed'], true)
        ) {
            throw new InvalidArgumentException('invalid_idempotency_record');
        }

        if ($executionStatus === 'completed' && $resultReference === null) {
            throw new InvalidArgumentException('completed_idempotency_result_missing');
        }
    }
}
