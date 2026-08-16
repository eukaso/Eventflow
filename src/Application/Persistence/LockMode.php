<?php

namespace EventFlow\Application\Persistence;

enum LockMode: string
{
    case NONE = 'none';
    case FOR_UPDATE = 'for_update';
}
