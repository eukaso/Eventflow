<?php

namespace EventFlow\Application\Observability;

use EventFlow\Application\Authorization\PrincipalContext;
use EventFlow\Application\Error\RequestId;
use EventFlow\Application\Persistence\EventScope;

interface DiagnosticExport
{
    public function export(PrincipalContext $principal, EventScope $scope, RequestId $requestId): DiagnosticBundle;
}
