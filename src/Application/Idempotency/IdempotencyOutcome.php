<?php

namespace EventFlow\Application\Idempotency;

final readonly class IdempotencyOutcome
{
    public function __construct(
        public bool $replayed,
        public IdempotencyResultReference $reference,
        public mixed $response = null,
    ) {
    }
}
