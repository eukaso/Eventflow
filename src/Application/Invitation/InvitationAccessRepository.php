<?php

namespace EventFlow\Application\Invitation;

use DateTimeImmutable;
use EventFlow\Application\Persistence\EventScope;

interface InvitationAccessRepository
{
    public function list(EventScope $scope, int $limit, ?int $afterInvitationId): InvitationPage;
    public function find(EventScope $scope, int $invitationId): ?InvitationRecord;
    public function lock(EventScope $scope, int $invitationId, bool $archived): ?InvitationRecord;
    public function activeAttendeeCount(EventScope $scope, int $invitationId): int;
    public function update(InvitationRecord $current, InvitationRecord $replacement, int $actorUserId, DateTimeImmutable $now): InvitationRecord;
    public function archive(InvitationRecord $current, int $actorUserId, DateTimeImmutable $now): InvitationRecord;
    public function restore(InvitationRecord $current, int $actorUserId, DateTimeImmutable $now): InvitationRecord;
    public function invalidateGuestAccess(EventScope $scope, int $invitationId, DateTimeImmutable $now): void;
}
