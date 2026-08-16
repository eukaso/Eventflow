<?php

namespace EventFlow\Application\Health;

enum OperationalStatus: string
{
    case HEALTHY = 'healthy';
    case DEGRADED = 'degraded';
    case UNAVAILABLE = 'unavailable';
}
