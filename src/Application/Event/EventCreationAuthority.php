<?php

namespace EventFlow\Application\Event;

interface EventCreationAuthority
{
    public function canCreateEvent(int $userId): bool;
}
