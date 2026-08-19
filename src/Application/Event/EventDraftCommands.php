<?php

namespace EventFlow\Application\Event;

use EventFlow\Application\Authorization\PrincipalContext;
use EventFlow\Application\Idempotency\IdempotencyOutcome;
use EventFlow\Application\Persistence\EventScope;

interface EventDraftCommands
{
    public function updateDraft(
        PrincipalContext $principal,
        EventScope $scope,
        EventDraftPatch $patch,
        string $idempotencyKey,
    ): IdempotencyOutcome;
}
