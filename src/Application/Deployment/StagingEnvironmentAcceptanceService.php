<?php

namespace EventFlow\Application\Deployment;

use EventFlow\Bootstrap\RuntimeRequirements;
use InvalidArgumentException;

final readonly class StagingEnvironmentAcceptanceService
{
    private const ROUTE_MARKERS = [
        '/eventflow/v1/system/health',
        '/eventflow/v1/system/readiness',
        '/eventflow/v1/events',
        '/eventflow/v1/venues',
        '/configuration',
        '/memberships',
        '/invitations',
        '/attendees',
        '/tables',
        '/seating-groups',
        '/reception/attendees',
        '/check-ins',
        '/communication-templates',
        '/campaigns',
        '/messages',
        '/imports',
        '/exports',
        '/privacy-actions',
        '/retention-holds',
        '/audit',
        '/diagnostics',
        '/public/invitations/bootstrap',
        '/public/invitation/response',
        '/webhooks/',
    ];

    public function evaluate(StagingEnvironmentSnapshot $snapshot, string $expectedVersion): StagingAcceptanceReport
    {
        if (preg_match('/^[0-9]+\.[0-9]+\.[0-9]+(?:-[a-z0-9.-]+)?$/', $expectedVersion) !== 1) {
            throw new InvalidArgumentException('staging_expected_version_invalid');
        }
        $checks = [
            $this->check('environment', $snapshot->environment === 'staging', 'staging_environment_required'),
            $this->check('debug_mode', !$snapshot->debugEnabled, 'debug_mode_forbidden'),
            $this->check('plugin_version', hash_equals($expectedVersion, $snapshot->pluginVersion), 'plugin_version_mismatch'),
            $this->check('php_runtime', version_compare($snapshot->phpVersion, RuntimeRequirements::MIN_PHP_VERSION, '>='), 'unsupported_php_version'),
            $this->check('wordpress_runtime', version_compare($snapshot->wordpressVersion, RuntimeRequirements::MIN_WORDPRESS_VERSION, '>='), 'unsupported_wordpress_version'),
            $this->check('database_runtime', $this->databaseSupported($snapshot), 'unsupported_database_version'),
            $this->check('database_charset', strtolower($snapshot->databaseCharset) === 'utf8mb4', 'database_utf8mb4_required'),
            $this->check('database_engine', strtolower($snapshot->databaseEngine) === 'innodb', 'database_innodb_required'),
            $this->check('https', $snapshot->https, 'verified_https_required'),
            $this->check('plugin_activation', $snapshot->pluginActive, 'plugin_not_active'),
            $this->check('plugin_filesystem', $snapshot->pluginFilesReadable, 'plugin_files_unreadable'),
            $this->check(
                'application_bootstrap',
                $snapshot->bootstrapHealthy && $snapshot->bootstrapReady && $snapshot->bootstrapState === 'ready',
                'application_bootstrap_not_ready',
            ),
            $this->check('cron_prerequisite', $snapshot->cronConfigured, 'cron_execution_not_configured'),
            $this->check(
                'protected_storage',
                $snapshot->protectedStorageConfigured
                    && $snapshot->protectedStorageOutsideWebRoot
                    && $snapshot->protectedStorageWritable,
                'protected_storage_not_ready',
            ),
            $this->check('external_secrets', $snapshot->externalSecretsAttested, 'external_secret_injection_not_attested'),
            $this->check('admin_composition', $snapshot->adminHooksRegistered, 'admin_hooks_not_registered'),
            $this->check('guest_composition', $snapshot->guestShortcodeRegistered, 'guest_shortcode_not_registered'),
            $this->check('rest_composition', $this->routesComplete($snapshot->restRoutes), 'rest_route_family_missing'),
        ];
        return new StagingAcceptanceReport($expectedVersion, $checks);
    }

    private function databaseSupported(StagingEnvironmentSnapshot $snapshot): bool
    {
        $product = strtolower($snapshot->databaseProduct);
        return ($product === 'mysql' && version_compare($snapshot->databaseVersion, '8.0', '>='))
            || ($product === 'mariadb' && version_compare($snapshot->databaseVersion, '10.11', '>='));
    }

    /** @param list<string> $routes */
    private function routesComplete(array $routes): bool
    {
        foreach (self::ROUTE_MARKERS as $marker) {
            $found = false;
            foreach ($routes as $route) {
                if (str_contains($route, $marker)) {
                    $found = true;
                    break;
                }
            }
            if (!$found) {
                return false;
            }
        }
        return true;
    }

    private function check(string $identifier, bool $passed, string $failureCode): StagingAcceptanceCheck
    {
        return new StagingAcceptanceCheck(
            $identifier,
            $passed ? StagingAcceptanceCheck::PASS : StagingAcceptanceCheck::FAIL,
            $passed ? 'ok' : $failureCode,
        );
    }
}
