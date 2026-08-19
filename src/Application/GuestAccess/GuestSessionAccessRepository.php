<?php

namespace EventFlow\Application\GuestAccess;

use DateTimeImmutable;
use EventFlow\Application\Attendee\RsvpResult;
use EventFlow\Application\Persistence\EventScope;

interface GuestSessionAccessRepository
{
    public function findContext(EventScope $scope, int $invitationId): ?GuestInvitationContext;
    public function findResponse(EventScope $scope, int $invitationId): ?RsvpResult;
    public function revokeSession(int $sessionId, EventScope $scope, int $invitationId, DateTimeImmutable $now): void;
}
