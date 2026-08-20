<?php

namespace EventFlow\Application\Privacy;

use EventFlow\Application\Authorization\PrincipalContext;
use EventFlow\Application\Persistence\EventScope;

interface PrivacyAccess
{
    public function listActions(PrincipalContext $principal, EventScope $scope, int $limit=50, ?int $afterActionId=null, ?string $status=null, ?string $kind=null, ?int $invitationId=null): PrivacyActionPage;
    public function readAction(PrincipalContext $principal, EventScope $scope, int $actionId): PrivacyActionRecord;
    public function listHolds(PrincipalContext $principal, EventScope $scope, int $limit=50, ?int $afterHoldId=null, ?string $status=null, ?int $invitationId=null): RetentionHoldPage;
    public function readHold(PrincipalContext $principal, EventScope $scope, int $holdId): RetentionHoldRecord;
}
