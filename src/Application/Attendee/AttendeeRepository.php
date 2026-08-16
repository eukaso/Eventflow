<?php

namespace EventFlow\Application\Attendee;

use DateTimeImmutable;
use EventFlow\Application\Persistence\EventScope;

interface AttendeeRepository
{
    public function lockInvitation(EventScope $scope, int $invitationId): ?RsvpInvitation;
    /** @return list<AttendeeRecord> */
    public function lockForInvitation(EventScope $scope, int $invitationId): array;
    public function create(EventScope $scope, int $invitationId, DesiredAttendee $desired, AttendanceStatus $status, ?int $actorUserId, DateTimeImmutable $now): AttendeeRecord;
    public function reconcile(AttendeeRecord $current, DesiredAttendee $desired, AttendanceStatus $status, ?int $actorUserId, DateTimeImmutable $now): AttendeeRecord;
    public function transition(AttendeeRecord $current, AttendanceStatus $status, ?int $actorUserId, DateTimeImmutable $now): AttendeeRecord;
    public function transferPrimary(AttendeeRecord $currentPrimary, AttendeeRecord $target, ?int $actorUserId, DateTimeImmutable $now): AttendeeRecord;
    public function updateResponse(RsvpInvitation $invitation, InvitationResponseStatus $status, DateTimeImmutable $now): RsvpInvitation;
    /** @param list<int> $activeAttendeeIds */
    public function synchronizeInvitationGroup(EventScope $scope, int $invitationId, array $activeAttendeeIds, DateTimeImmutable $now): void;
}
