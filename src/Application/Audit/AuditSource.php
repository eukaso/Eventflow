<?php

namespace EventFlow\Application\Audit;

enum AuditSource: string
{
    case APPLICATION = 'application';
    case ADMIN_UI = 'admin_ui';
    case GUEST_PORTAL = 'guest_portal';
    case REST_API = 'rest_api';
    case BACKGROUND_JOB = 'background_job';
    case WEBHOOK = 'webhook';
    case MIGRATION = 'migration';
    case SYSTEM = 'system';
}
