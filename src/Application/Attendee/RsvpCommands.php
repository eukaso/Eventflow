<?php

namespace EventFlow\Application\Attendee;

use EventFlow\Application\Authorization\PrincipalContext;
use EventFlow\Application\Idempotency\IdempotencyOutcome;

interface RsvpCommands
{
    public function submitRsvp(PrincipalContext $principal, SubmitRsvp $command, string $idempotencyKey): IdempotencyOutcome;
}
