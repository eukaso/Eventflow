<?php

namespace EventFlow\Application\Idempotency;

final readonly class IdempotentOperationResult
{
    public function __construct(
        public IdempotencyResultReference $reference,
        public mixed $response,
        public bool $sensitiveReturnOnce = false,
    ) {
    }
}
