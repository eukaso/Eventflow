<?php

namespace EventFlow\Bootstrap;

enum BootstrapState: string
{
    case READY = 'ready';
    case DEGRADED = 'degraded';
    case MIGRATION_REQUIRED = 'migration_required';
    case INCOMPATIBLE_SCHEMA = 'incompatible_schema';
    case INVALID_CONFIGURATION = 'invalid_configuration';
    case UNSUPPORTED_RUNTIME = 'unsupported_runtime';
    case FAILED = 'failed';
}
