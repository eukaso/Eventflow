<?php

namespace EventFlow\Application\Error;

use InvalidArgumentException;

final readonly class RetryAfterDetails implements PublicErrorDetails
{
    public function __construct(public int $retryAfterSeconds)
    {
        if ($retryAfterSeconds < 1 || $retryAfterSeconds > 86400) {
            throw new InvalidArgumentException('invalid_retry_after_details');
        }
    }

    public function kind(): ErrorDetailKind
    {
        return ErrorDetailKind::RETRY_AFTER;
    }

    public function toArray(): array
    {
        return ['retry_after_seconds' => $this->retryAfterSeconds];
    }
}
