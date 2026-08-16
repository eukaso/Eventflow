<?php

namespace EventFlow\Application\Attendee;

enum AttendanceStatus: string
{
    case PENDING = 'pending';
    case CONFIRMED = 'confirmed';
    case DECLINED = 'declined';
    case CANCELLED = 'cancelled';
}
