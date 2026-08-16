<?php

namespace EventFlow\Application\Membership;

use RuntimeException;

final class MembershipException extends RuntimeException
{
    public function __construct(public readonly string $safeCode)
    {
        parent::__construct($safeCode);
    }
}
