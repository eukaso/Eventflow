<?php

namespace EventFlow\Application\Invitation;

use DateTimeImmutable;
use EventFlow\Application\Authorization\PrincipalContext;
use EventFlow\Application\Idempotency\IdempotencyOutcome;
use EventFlow\Application\Persistence\EventScope;

interface InvitationCommands
{
    public function create(PrincipalContext $principal, CreateInvitation $command, string $idempotencyKey): IdempotencyOutcome;
    public function rotateCredential(PrincipalContext $principal, EventScope $scope, int $invitationId, ?DateTimeImmutable $expiresAt, string $idempotencyKey): IdempotencyOutcome;
    public function reactivate(PrincipalContext $principal, EventScope $scope, int $invitationId, ?DateTimeImmutable $expiresAt, string $idempotencyKey): IdempotencyOutcome;
    public function revoke(PrincipalContext $principal, EventScope $scope, int $invitationId, string $idempotencyKey): IdempotencyOutcome;
}
