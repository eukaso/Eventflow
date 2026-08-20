<?php

namespace EventFlow\Application\Privacy;

use EventFlow\Application\Authorization\AuthorizationService;
use EventFlow\Application\Authorization\Capability;
use EventFlow\Application\Authorization\PrincipalContext;
use EventFlow\Application\Persistence\EventScope;

final readonly class PrivacyAccessService implements PrivacyAccess
{
    private const ACTION_STATUSES = ['pending','processing','failed','completed'];
    private const ACTION_KINDS = ['explicit','retention'];
    private const HOLD_STATUSES = ['active','released'];

    public function __construct(private PrivacyAccessRepository $privacy, private AuthorizationService $authorization) {}

    public function listActions(PrincipalContext $principal, EventScope $scope, int $limit=50, ?int $afterActionId=null, ?string $status=null, ?string $kind=null, ?int $invitationId=null): PrivacyActionPage
    {
        $this->validate($limit,$afterActionId,$invitationId);
        if (($status!==null&&!in_array($status,self::ACTION_STATUSES,true))||($kind!==null&&!in_array($kind,self::ACTION_KINDS,true))) throw new PrivacyException('privacy_query_invalid');
        $this->authorize($principal,$scope);
        return $this->privacy->listActions($scope,$limit,$afterActionId,$status,$kind,$invitationId);
    }

    public function readAction(PrincipalContext $principal, EventScope $scope, int $actionId): PrivacyActionRecord
    {
        $this->authorize($principal,$scope);
        return $this->privacy->findAction($scope,$actionId) ?? throw new PrivacyException('resource_not_found');
    }

    public function listHolds(PrincipalContext $principal, EventScope $scope, int $limit=50, ?int $afterHoldId=null, ?string $status=null, ?int $invitationId=null): RetentionHoldPage
    {
        $this->validate($limit,$afterHoldId,$invitationId);
        if ($status!==null&&!in_array($status,self::HOLD_STATUSES,true)) throw new PrivacyException('privacy_query_invalid');
        $this->authorize($principal,$scope);
        return $this->privacy->listHolds($scope,$limit,$afterHoldId,$status,$invitationId);
    }

    public function readHold(PrincipalContext $principal, EventScope $scope, int $holdId): RetentionHoldRecord
    {
        $this->authorize($principal,$scope);
        return $this->privacy->findHold($scope,$holdId) ?? throw new PrivacyException('resource_not_found');
    }

    private function authorize(PrincipalContext $principal, EventScope $scope): void { $this->authorization->requireEventCapability($principal,$scope,Capability::MANAGE_PRIVACY); }
    private function validate(int $limit, ?int $after, ?int $invitationId): void { if($limit<1||$limit>100||($after!==null&&$after<1)||($invitationId!==null&&$invitationId<1))throw new PrivacyException('privacy_query_invalid'); }
}
