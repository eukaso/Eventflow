<?php

namespace EventFlow\Application\GuestAccess;

use EventFlow\Application\Authorization\PrincipalContext;

interface GuestSessionAuthenticator
{
    public function authenticate(
        string $rawSessionToken,
        ?string $rawCsrfToken = null,
        bool $stateChanging = false,
        bool $sameOrigin = true,
    ): PrincipalContext;
}
