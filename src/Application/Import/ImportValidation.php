<?php

namespace EventFlow\Application\Import;

use EventFlow\Application\Authorization\PrincipalContext;
use EventFlow\Application\Idempotency\IdempotencyOutcome;
use EventFlow\Application\Persistence\EventScope;

interface ImportValidation
{
    public function validate(PrincipalContext $principal, EventScope $scope, int $jobId, ImportMapping $mapping, string $idempotencyKey, ?int $expectedRevision = null): IdempotencyOutcome;
}
