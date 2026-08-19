<?php

namespace EventFlow\Application\EventConfiguration;

use EventFlow\Application\Authorization\PrincipalContext;
use EventFlow\Application\Idempotency\IdempotencyOutcome;
use EventFlow\Application\Persistence\EventScope;

interface EventConfigurationOperations
{
    public function read(PrincipalContext $principal, EventScope $scope): EventConfigurationRecord;
    public function update(PrincipalContext $principal, EventScope $scope, EventConfigurationPatch $patch, string $idempotencyKey): IdempotencyOutcome;
}
