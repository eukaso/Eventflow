<?php

namespace EventFlow\Application\Health;

enum HealthCode: string
{
    case OK = 'ok';
    case CHECK_FAILED = 'check_failed';
    case DATABASE_UNAVAILABLE = 'database_unavailable';
    case SCHEMA_MIGRATION_REQUIRED = 'schema_migration_required';
    case APPLICATION_SCHEMA_INCOMPATIBLE = 'application_schema_incompatible';
    case SCHEMA_COMPATIBILITY_UNKNOWN = 'schema_compatibility_unknown';
    case PRIVACY_RECONCILIATION_REQUIRED = 'privacy_reconciliation_required';
    case PROVIDER_UNAVAILABLE = 'provider_unavailable';
    case IMPORT_DEGRADED = 'import_degraded';
    case RECOMMENDATION_DEGRADED = 'recommendation_degraded';
    case JOB_PROCESSING_DEGRADED = 'job_processing_degraded';
    case BOOTSTRAP_FAILURE = 'bootstrap_failure';
    case BOOTSTRAP_UNAVAILABLE = 'bootstrap_unavailable';
}
