<?php

namespace EventFlow\Application\GuestAccess;

use DateTimeImmutable;
use EventFlow\Application\Invitation\InvitationRecord;
use EventFlow\Application\Persistence\EventScope;

interface GuestAccessRepository
{
    public function resolveBootstrapCredential(GuestCredentialType $type, string $digest, DateTimeImmutable $now): ?InvitationRecord;
    public function markCredentialUsed(GuestCredentialType $type, string $digest, InvitationRecord $invitation, DateTimeImmutable $now): void;
    public function createSession(InvitationRecord $invitation, string $sessionDigest, string $csrfDigest, DateTimeImmutable $expiresAt, DateTimeImmutable $now): GuestSessionRecord;
    public function findCurrentSession(string $sessionDigest, DateTimeImmutable $now): ?GuestSessionRecord;
    public function touchSession(GuestSessionRecord $session, DateTimeImmutable $now): void;
    public function issueMessageLink(EventScope $scope, int $invitationId, int $messageId, string $purpose, string $digest, int $tokenVersion, DateTimeImmutable $expiresAt, DateTimeImmutable $now): int;
}
