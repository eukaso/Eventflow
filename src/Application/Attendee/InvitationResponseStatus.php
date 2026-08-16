<?php

namespace EventFlow\Application\Attendee;

enum InvitationResponseStatus: string
{
    case PENDING = 'pending';
    case ACCEPTED = 'accepted';
    case DECLINED = 'declined';
}
