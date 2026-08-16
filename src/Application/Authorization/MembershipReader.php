<?php

namespace EventFlow\Application\Authorization;

use EventFlow\Application\Persistence\EventScope;

interface MembershipReader
{
    /** Always read current authoritative membership state; do not cache across requests. */
    public function findCurrent(EventScope $eventScope, int $userId): ?MembershipSnapshot;
}
