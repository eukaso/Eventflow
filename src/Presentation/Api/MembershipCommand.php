<?php

namespace EventFlow\Presentation\Api;

enum MembershipCommand: string
{
    case SUSPEND = 'suspend';
    case REACTIVATE = 'reactivate';
    case REVOKE = 'revoke';
}
