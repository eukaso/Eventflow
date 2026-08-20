<?php

namespace EventFlow\Application\Privacy;

use EventFlow\Application\Authorization\PrincipalContext;
use EventFlow\Application\Idempotency\IdempotencyOutcome;
use EventFlow\Application\Persistence\EventScope;

interface PrivacyCommands
{
    public function request(PrincipalContext $principal, EventScope $scope, int $invitationId, string $policyVersion, string $purpose, string $idempotencyKey): IdempotencyOutcome;
    public function placeHold(PrincipalContext $principal, EventScope $scope, ?int $invitationId, string $policyVersion, string $reason, string $idempotencyKey): IdempotencyOutcome;
    public function releaseHold(PrincipalContext $principal, EventScope $scope, int $holdId, string $idempotencyKey): IdempotencyOutcome;
}
