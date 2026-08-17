<?php

namespace EventFlow\Presentation\Api;

enum EventLifecycleCommand: string
{
    case ACTIVATE = 'activate';
    case COMPLETE = 'complete';
    case CANCEL = 'cancel';
    case ARCHIVE = 'archive';
    case RESTORE = 'restore';
}
