<?php

namespace EventFlow\Application\Invitation;

enum InvitationStatus: string
{
    case ACTIVE = 'active';
    case REVOKED = 'revoked';
}
