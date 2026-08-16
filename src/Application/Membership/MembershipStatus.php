<?php

namespace EventFlow\Application\Membership;

enum MembershipStatus: string
{
    case INVITED = 'invited';
    case ACTIVE = 'active';
    case SUSPENDED = 'suspended';
    case REVOKED = 'revoked';
    case EXPIRED = 'expired';
}
