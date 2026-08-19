<?php

namespace EventFlow\Application\Event;

use EventFlow\Application\Authorization\PrincipalContext;
use EventFlow\Application\Persistence\EventScope;

interface EventQueries
{
    public function listAccessible(
        PrincipalContext $principal,
        int $limit = 50,
        ?int $afterEventId = null,
    ): EventPage;

    public function read(PrincipalContext $principal, EventScope $scope): EventRecord;
}
