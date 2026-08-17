<?php

namespace EventFlow\Application\Attendee;

use EventFlow\Application\Authorization\PrincipalContext;
use EventFlow\Application\Idempotency\IdempotencyOutcome;
use EventFlow\Application\Persistence\EventScope;

interface AttendeeCommands
{
    public function createAttendee(PrincipalContext $principal, EventScope $scope, int $invitationId, DesiredAttendee $desired, string $idempotencyKey): IdempotencyOutcome;
    public function updateAttendee(PrincipalContext $principal, EventScope $scope, int $invitationId, int $attendeeId, DesiredAttendee $desired, string $idempotencyKey): IdempotencyOutcome;
    public function cancel(PrincipalContext $principal, EventScope $scope, int $invitationId, int $attendeeId, string $idempotencyKey): IdempotencyOutcome;
    public function restore(PrincipalContext $principal, EventScope $scope, int $invitationId, int $attendeeId, string $idempotencyKey): IdempotencyOutcome;
    public function transferPrimary(PrincipalContext $principal, EventScope $scope, int $invitationId, int $expectedPrimaryId, int $targetId, string $idempotencyKey): IdempotencyOutcome;
}
