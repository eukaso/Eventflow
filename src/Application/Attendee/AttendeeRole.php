<?php

namespace EventFlow\Application\Attendee;

enum AttendeeRole: string
{
    case PRIMARY = 'primary';
    case COMPANION = 'companion';
}
