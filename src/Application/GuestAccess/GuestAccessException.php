<?php

namespace EventFlow\Application\GuestAccess;

use RuntimeException;

final class GuestAccessException extends RuntimeException
{
    public function __construct(public readonly string $safeCode)
    {
        parent::__construct($safeCode);
    }
}
