<?php

namespace EventFlow\Application\Authorization;

interface GlobalRecoveryAuthority
{
    /** Dedicated break-glass authority; never a general Event capability bypass. */
    public function canRecoverPrimaryOwnership(int $userId): bool;
}
