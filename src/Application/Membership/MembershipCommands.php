<?php

namespace EventFlow\Application\Membership;

use EventFlow\Application\Authorization\PrincipalContext;
use EventFlow\Application\Idempotency\IdempotencyOutcome;
use EventFlow\Application\Persistence\EventScope;

interface MembershipCommands
{
    public function grant(PrincipalContext $principal, GrantMembership $command, string $idempotencyKey): IdempotencyOutcome;
    public function change(PrincipalContext $principal, ChangeMembership $command, string $idempotencyKey): IdempotencyOutcome;
    public function suspend(PrincipalContext $principal, EventScope $scope, int $membershipId, string $idempotencyKey): IdempotencyOutcome;
    public function reactivate(PrincipalContext $principal, EventScope $scope, int $membershipId, string $idempotencyKey): IdempotencyOutcome;
    public function revoke(PrincipalContext $principal, EventScope $scope, int $membershipId, string $idempotencyKey): IdempotencyOutcome;
    public function transferPrimaryOwner(PrincipalContext $principal, TransferPrimaryOwner $command, string $idempotencyKey): IdempotencyOutcome;
}
