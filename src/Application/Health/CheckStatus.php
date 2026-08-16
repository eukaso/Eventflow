<?php

namespace EventFlow\Application\Health;

enum CheckStatus: string
{
    case UP = 'up';
    case DEGRADED = 'degraded';
    case DOWN = 'down';
}
