<?php

namespace EventFlow\Application\Invitation;

use EventFlow\Application\Authorization\PrincipalContext;
use EventFlow\Application\Idempotency\IdempotencyOutcome;
use EventFlow\Application\Persistence\EventScope;

interface InvitationOperations
{
    public function list(PrincipalContext $principal, EventScope $scope, int $limit = 50, ?int $afterInvitationId = null): InvitationPage;
    public function read(PrincipalContext $principal, EventScope $scope, int $invitationId): InvitationRecord;
    public function update(PrincipalContext $principal, EventScope $scope, int $invitationId, InvitationPatch $patch, string $idempotencyKey): IdempotencyOutcome;
    public function applyCompanionRollout(PrincipalContext $principal, EventScope $scope, string $idempotencyKey): IdempotencyOutcome;
    public function archive(PrincipalContext $principal, EventScope $scope, int $invitationId, string $idempotencyKey): IdempotencyOutcome;
    public function restore(PrincipalContext $principal, EventScope $scope, int $invitationId, string $idempotencyKey): IdempotencyOutcome;
}
