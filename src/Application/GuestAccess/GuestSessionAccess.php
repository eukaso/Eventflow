<?php

namespace EventFlow\Application\GuestAccess;

use EventFlow\Application\Attendee\RsvpResult;
use EventFlow\Application\Authorization\PrincipalContext;

interface GuestSessionAccess
{
    public function context(PrincipalContext $principal): GuestInvitationContext;
    public function response(PrincipalContext $principal): RsvpResult;
    public function logout(PrincipalContext $principal): void;
}
