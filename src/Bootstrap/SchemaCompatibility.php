<?php

namespace EventFlow\Bootstrap;

enum SchemaCompatibility: string
{
    case COMPATIBLE = 'compatible';
    case MIGRATION_REQUIRED = 'migration_required';
    case APPLICATION_TOO_OLD = 'application_too_old';
    case UNKNOWN = 'unknown';
}
