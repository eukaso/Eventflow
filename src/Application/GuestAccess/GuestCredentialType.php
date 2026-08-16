<?php

namespace EventFlow\Application\GuestAccess;

enum GuestCredentialType: string
{
    case INVITATION = 'invitation';
    case MESSAGE_LINK = 'message_link';
}
