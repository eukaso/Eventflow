<?php

namespace EventFlow\Application\Idempotency;

use InvalidArgumentException;

final readonly class IdempotencyResultReference
{
    public function __construct(
        public string $entityType,
        public int $entityId,
        public int $responseStatusCode,
    ) {
        if (!preg_match('/^[a-z][a-z0-9_]{1,63}$/', $entityType) || $entityId < 1) {
            throw new InvalidArgumentException('invalid_idempotency_result_reference');
        }

        if ($responseStatusCode < 200 || $responseStatusCode > 599) {
            throw new InvalidArgumentException('invalid_idempotency_response_status');
        }
    }
}
