<?php

namespace EventFlow\Application\Authorization;

enum GuestPermission: string
{
    case VIEW_INVITATION = 'view_invitation';
    case MANAGE_RSVP = 'manage_rsvp';
    case LOG_OUT = 'log_out';
}
