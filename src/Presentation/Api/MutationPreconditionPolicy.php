<?php

namespace EventFlow\Presentation\Api;

enum MutationPreconditionPolicy
{
    case NONE;
    case IDEMPOTENCY_KEY;
    case IF_MATCH;
    case IF_MATCH_AND_IDEMPOTENCY_KEY;

    public function requiresIdempotencyKey(): bool
    {
        return $this === self::IDEMPOTENCY_KEY || $this === self::IF_MATCH_AND_IDEMPOTENCY_KEY;
    }

    public function requiresIfMatch(): bool
    {
        return $this === self::IF_MATCH || $this === self::IF_MATCH_AND_IDEMPOTENCY_KEY;
    }
}
