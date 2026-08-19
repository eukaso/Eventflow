<?php

namespace EventFlow\Presentation\Api;

enum InvitationAccessCommand: string
{
    case ARCHIVE = 'archive';
    case RESTORE = 'restore';
}
