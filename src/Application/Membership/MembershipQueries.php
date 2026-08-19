<?php

namespace EventFlow\Application\Membership;

use EventFlow\Application\Authorization\PrincipalContext;
use EventFlow\Application\Persistence\EventScope;

interface MembershipQueries
{
    public function list(
        PrincipalContext $principal,
        EventScope $scope,
        int $limit = 50,
        ?int $afterMembershipId = null,
    ): MembershipPage;
}
