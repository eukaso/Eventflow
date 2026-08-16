<?php

namespace EventFlow\Application\GuestAccess;

final readonly class GuestSessionCredentials
{
    public function __construct(
        public GuestSessionRecord $session,
        public string $rawSessionToken,
        public string $rawCsrfToken,
    ) {
        if (!preg_match('/^[a-f0-9]{64}$/', $rawSessionToken) || !preg_match('/^[a-f0-9]{64}$/', $rawCsrfToken)) {
            throw new \InvalidArgumentException('invalid_guest_session_credentials');
        }
    }
}
