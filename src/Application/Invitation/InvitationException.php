<?php

namespace EventFlow\Application\Invitation;

use RuntimeException;

final class InvitationException extends RuntimeException
{
    public function __construct(public readonly string $safeCode)
    {
        parent::__construct($safeCode);
    }
}
