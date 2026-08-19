<?php

namespace EventFlow\Application\Membership;

use EventFlow\Application\Authorization\{AuthorizationService, Capability, PrincipalContext};
use EventFlow\Application\Persistence\EventScope;

final readonly class MembershipQueryService implements MembershipQueries
{
    public function __construct(
        private MembershipQueryRepository $memberships,
        private AuthorizationService $authorization,
    ) {
    }

    public function list(
        PrincipalContext $principal,
        EventScope $scope,
        int $limit = 50,
        ?int $afterMembershipId = null,
    ): MembershipPage {
        $this->authorization->requireEventCapability(
            $principal,
            $scope,
            Capability::MANAGE_STAFF_MEMBERSHIPS,
        );
        if ($limit < 1 || $limit > 100 || ($afterMembershipId !== null && $afterMembershipId < 1)) {
            throw new MembershipException('validation_failed');
        }
        return $this->memberships->list($scope, $limit, $afterMembershipId);
    }
}
