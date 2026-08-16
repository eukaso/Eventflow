<?php

namespace EventFlow\Application\Membership;

use DateTimeImmutable;
use EventFlow\Application\Authorization\EventRole;
use EventFlow\Application\Persistence\EventScope;

interface MembershipRepository
{
    public function findForUpdate(EventScope $scope, int $membershipId): ?MembershipRecord;

    public function findByUserForUpdate(EventScope $scope, int $userId): ?MembershipRecord;

    public function findPrimaryOwnerForUpdate(EventScope $scope): ?MembershipRecord;

    public function grant(GrantMembership $command, ?int $actorUserId, DateTimeImmutable $now): MembershipRecord;

    public function change(MembershipRecord $current, EventRole $role, ?DateTimeImmutable $expiresAt, DateTimeImmutable $now): MembershipRecord;

    public function transitionStatus(MembershipRecord $current, MembershipStatus $status, DateTimeImmutable $now): MembershipRecord;

    public function transferPrimaryOwner(MembershipRecord $current, MembershipRecord $target, DateTimeImmutable $now): MembershipRecord;
}
