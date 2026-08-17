<?php

namespace EventFlow\Presentation\Api;

enum AttendeeCommand: string
{
    case CANCEL = 'cancel';
    case RESTORE = 'restore';
}
