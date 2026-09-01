<?php

namespace EventFlow\Application\CheckIn;

use EventFlow\Application\Authorization\PrincipalContext;
use EventFlow\Application\Persistence\EventScope;

interface ReceptionSearch
{
    /** @return list<ReceptionAttendee> */
    public function search(PrincipalContext $principal, EventScope $scope, string $query, int $limit = 20): array;
    public function lookup(PrincipalContext $principal, EventScope $scope, string $code): ReceptionAttendee;
}
