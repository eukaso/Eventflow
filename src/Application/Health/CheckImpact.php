<?php

namespace EventFlow\Application\Health;

enum CheckImpact: string
{
    case CORE_READINESS = 'core_readiness';
    case OPTIONAL_CAPABILITY = 'optional_capability';
}
