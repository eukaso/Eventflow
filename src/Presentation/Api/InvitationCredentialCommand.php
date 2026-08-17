<?php

namespace EventFlow\Presentation\Api;

enum InvitationCredentialCommand: string
{
    case ACTIVATE = 'activate';
    case ROTATE_TOKEN = 'rotate-token';
}
