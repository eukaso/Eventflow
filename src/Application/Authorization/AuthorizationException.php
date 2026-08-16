<?php

namespace EventFlow\Application\Authorization;

use RuntimeException;

final class AuthorizationException extends RuntimeException
{
    public function __construct(public readonly string $safeCode)
    {
        parent::__construct($safeCode);
    }
}
