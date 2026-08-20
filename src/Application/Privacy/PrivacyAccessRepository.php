<?php

namespace EventFlow\Application\Privacy;

use EventFlow\Application\Persistence\EventScope;

interface PrivacyAccessRepository
{
    public function listActions(EventScope $scope, int $limit, ?int $afterActionId, ?string $status, ?string $kind, ?int $invitationId): PrivacyActionPage;
    public function findAction(EventScope $scope, int $actionId): ?PrivacyActionRecord;
    public function listHolds(EventScope $scope, int $limit, ?int $afterHoldId, ?string $status, ?int $invitationId): RetentionHoldPage;
    public function findHold(EventScope $scope, int $holdId): ?RetentionHoldRecord;
}
