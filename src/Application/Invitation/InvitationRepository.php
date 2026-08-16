<?php

namespace EventFlow\Application\Invitation;

use DateTimeImmutable;
use EventFlow\Application\Persistence\EventScope;

interface InvitationRepository
{
    public function create(CreateInvitation $command, string $code, string $tokenDigest, ?int $actorUserId, DateTimeImmutable $now): InvitationRecord;
    public function lock(EventScope $scope, int $invitationId): ?InvitationRecord;
    public function rotateCredential(InvitationRecord $invitation, string $tokenDigest, ?DateTimeImmutable $expiresAt, ?int $actorUserId, DateTimeImmutable $now): InvitationRecord;
    public function revoke(InvitationRecord $invitation, ?int $actorUserId, DateTimeImmutable $now): InvitationRecord;
    public function reactivate(InvitationRecord $invitation, string $tokenDigest, ?DateTimeImmutable $expiresAt, ?int $actorUserId, DateTimeImmutable $now): InvitationRecord;
    public function invalidateGuestAccess(EventScope $scope, int $invitationId, DateTimeImmutable $now): void;
}
