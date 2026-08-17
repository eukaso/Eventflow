<?php

namespace EventFlow\Application\Authorization;

use EventFlow\Application\Persistence\EventScope;

interface EventCapabilityGate
{
    public function allows(EventScope $scope, Capability $capability): bool;
}
