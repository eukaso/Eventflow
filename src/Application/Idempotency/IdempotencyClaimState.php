<?php

namespace EventFlow\Application\Idempotency;

enum IdempotencyClaimState: string
{
    case ACQUIRED = 'acquired';
    case REPLAY = 'replay';
    case CONFLICT = 'conflict';
    case IN_PROGRESS = 'in_progress';
}
