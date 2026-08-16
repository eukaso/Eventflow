<?php

namespace EventFlow\Application\Error;

use InvalidArgumentException;

final readonly class RequestId
{
    public function __construct(public string $value)
    {
        if (!preg_match('/^[A-Za-z0-9][A-Za-z0-9_-]{15,99}$/', $value)) {
            throw new InvalidArgumentException('invalid_request_id');
        }
    }
}
