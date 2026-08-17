<?php

namespace EventFlow\Application\Event;

use EventFlow\Application\Authorization\PrincipalContext;
use EventFlow\Application\Idempotency\IdempotencyOutcome;
use EventFlow\Application\Persistence\EventScope;

interface EventLifecycleCommands
{
    public function create(PrincipalContext $principal, CreateEvent $event, string $idempotencyKey): IdempotencyOutcome;
    public function activate(PrincipalContext $principal, EventScope $scope, string $idempotencyKey): IdempotencyOutcome;
    public function complete(PrincipalContext $principal, EventScope $scope, string $idempotencyKey): IdempotencyOutcome;
    public function cancel(PrincipalContext $principal, EventScope $scope, string $idempotencyKey): IdempotencyOutcome;
    public function archive(PrincipalContext $principal, EventScope $scope, string $idempotencyKey): IdempotencyOutcome;
    public function restore(PrincipalContext $principal, EventScope $scope, string $idempotencyKey): IdempotencyOutcome;
}
