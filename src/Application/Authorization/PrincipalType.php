<?php

namespace EventFlow\Application\Authorization;

enum PrincipalType: string
{
    case ANONYMOUS = 'anonymous';
    case WORDPRESS_USER = 'wordpress_user';
    case GUEST = 'guest';
    case BACKGROUND_JOB = 'background_job';
    case PROVIDER_WEBHOOK = 'provider_webhook';
    case MIGRATION = 'migration';
    case SYSTEM = 'system';
}
