<?php

namespace EventFlow\Application\Authorization;

use EventFlow\Application\Persistence\EventScope;

final readonly class PermissiveEventCapabilityGate implements EventCapabilityGate
{
    public function allows(EventScope $scope, Capability $capability): bool
    {
        return true;
    }
}
