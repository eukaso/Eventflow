<?php

namespace EventFlow\Application\GuestAccess;

final readonly class IssuedGuestLink
{
    public function __construct(public int $credentialId, public string $rawCredential)
    {
        if ($credentialId < 1 || !preg_match('/^(?:[a-f0-9]{32}|[a-f0-9]{64})$/', $rawCredential)) {
            throw new \InvalidArgumentException('invalid_issued_guest_link');
        }
    }
}
