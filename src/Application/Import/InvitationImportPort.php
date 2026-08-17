<?php

namespace EventFlow\Application\Import;

use EventFlow\Application\Authorization\PrincipalContext;
use EventFlow\Application\Idempotency\IdempotencyOutcome;
use EventFlow\Application\Invitation\CreateInvitation;

interface InvitationImportPort
{
    public function createImported(PrincipalContext $principal, CreateInvitation $command, string $idempotencyKey): IdempotencyOutcome;
}
