<?php

namespace EventFlow\Application\Membership;

use EventFlow\Application\Persistence\EventScope;

interface MembershipQueryRepository
{
    public function list(EventScope $scope, int $limit, ?int $afterMembershipId): MembershipPage;
}
