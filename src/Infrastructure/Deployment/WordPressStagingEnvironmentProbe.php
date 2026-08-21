<?php

namespace EventFlow\Infrastructure\Deployment;

use Closure;
use EventFlow\Application\Deployment\StagingEnvironmentSnapshot;
use EventFlow\Bootstrap\ApplicationBootstrap;
use ReflectionFunction;
use Throwable;

final readonly class WordPressStagingEnvironmentProbe
{
    public function capture(): StagingEnvironmentSnapshot
    {
        global $wpdb, $wp_version;
        $bootstrap = ApplicationBootstrap::result();
        [$databaseProduct, $databaseVersion] = $this->databaseIdentity(is_object($wpdb) ? $wpdb : null);
        $pluginFile = defined('EVENTFLOW_PLUGIN_FILE') ? (string) EVENTFLOW_PLUGIN_FILE : '';
        $pluginDirectory = defined('EVENTFLOW_PLUGIN_DIR') ? (string) EVENTFLOW_PLUGIN_DIR : '';
        $storage = defined('EVENTFLOW_PROTECTED_EXPORT_DIR') ? (string) EVENTFLOW_PROTECTED_EXPORT_DIR : '';

        return new StagingEnvironmentSnapshot(
            environment: defined('EVENTFLOW_ENV') ? (string) EVENTFLOW_ENV : '',
            debugEnabled: defined('EVENTFLOW_DEBUG') && EVENTFLOW_DEBUG === true,
            pluginVersion: defined('EVENTFLOW_VERSION') ? (string) EVENTFLOW_VERSION : '',
            phpVersion: PHP_VERSION,
            wordpressVersion: isset($wp_version) ? (string) $wp_version : '',
            databaseProduct: $databaseProduct,
            databaseVersion: $databaseVersion,
            databaseCharset: is_object($wpdb) && isset($wpdb->charset) ? (string) $wpdb->charset : '',
            databaseEngine: $this->databaseEngine(is_object($wpdb) ? $wpdb : null),
            https: function_exists('is_ssl') && is_ssl()
                && function_exists('home_url') && str_starts_with(strtolower((string) home_url('/')), 'https://'),
            pluginActive: $this->pluginActive($pluginFile),
            pluginFilesReadable: $pluginFile !== ''
                && $pluginDirectory !== ''
                && is_readable($pluginFile)
                && is_readable($pluginDirectory . '/src')
                && is_readable($pluginDirectory . '/assets/admin')
                && is_readable($pluginDirectory . '/assets/guest'),
            bootstrapHealthy: $bootstrap?->healthy ?? false,
            bootstrapReady: $bootstrap?->ready ?? false,
            bootstrapState: $bootstrap?->state->value ?? 'unavailable',
            cronConfigured: !(defined('DISABLE_WP_CRON') && DISABLE_WP_CRON === true)
                || (defined('EVENTFLOW_EXTERNAL_CRON') && EVENTFLOW_EXTERNAL_CRON === true),
            protectedStorageConfigured: $storage !== '' && $this->absolute($storage),
            protectedStorageOutsideWebRoot: $this->outsideWebRoot($storage),
            protectedStorageWritable: is_dir($storage) && is_writable($storage) && !is_link($storage),
            externalSecretsAttested: defined('EVENTFLOW_SECRETS_EXTERNAL') && EVENTFLOW_SECRETS_EXTERNAL === true,
            adminHooksRegistered: $this->hookOwnsMethod('admin_menu', 'registerMenu')
                && $this->hookOwnsMethod('admin_enqueue_scripts', 'enqueueAssets'),
            guestShortcodeRegistered: function_exists('shortcode_exists')
                && shortcode_exists('eventflow_rsvp'),
            restRoutes: $this->restRoutes(),
        );
    }

    /** @return array{string,string} */
    private function databaseIdentity(?object $database): array
    {
        if ($database === null || !method_exists($database, 'db_server_info')) {
            return ['', '0'];
        }
        try {
            $server = (string) $database->db_server_info();
            $product = stripos($server, 'mariadb') !== false ? 'mariadb' : 'mysql';
            preg_match_all('/[0-9]+(?:\.[0-9]+){1,3}/', $server, $matches);
            $versions = $matches[0] ?? [];
            $version = $product === 'mariadb' ? end($versions) : reset($versions);
            return [$product, is_string($version) ? $version : '0'];
        } catch (Throwable) {
            return ['', '0'];
        }
    }

    private function databaseEngine(?object $database): string
    {
        if ($database === null || !method_exists($database, 'get_var')) {
            return '';
        }
        try {
            return (string) $database->get_var('SELECT @@default_storage_engine');
        } catch (Throwable) {
            return '';
        }
    }

    private function pluginActive(string $pluginFile): bool
    {
        if ($pluginFile === '' || !function_exists('plugin_basename') || !function_exists('get_option')) {
            return false;
        }
        $plugin = plugin_basename($pluginFile);
        $active = get_option('active_plugins', []);
        if (is_array($active) && in_array($plugin, $active, true)) {
            return true;
        }
        if (function_exists('get_site_option')) {
            $network = get_site_option('active_sitewide_plugins', []);
            return is_array($network) && isset($network[$plugin]);
        }
        return false;
    }

    private function absolute(string $path): bool
    {
        return str_starts_with($path, '/') || preg_match('/^[A-Za-z]:[\\\\\/]/', $path) === 1;
    }

    private function outsideWebRoot(string $storage): bool
    {
        if ($storage === '' || !defined('ABSPATH')) {
            return false;
        }
        $resolvedStorage = realpath($storage);
        $resolvedWebRoot = realpath((string) ABSPATH);
        if ($resolvedStorage === false || $resolvedWebRoot === false) {
            return false;
        }
        $storagePath = rtrim(str_replace('\\', '/', strtolower($resolvedStorage)), '/') . '/';
        $webRoot = rtrim(str_replace('\\', '/', strtolower($resolvedWebRoot)), '/') . '/';
        return !str_starts_with($storagePath, $webRoot);
    }

    private function hookOwnsMethod(string $hook, string $method): bool
    {
        global $wp_filter;
        $callbacks = isset($wp_filter[$hook]) && is_object($wp_filter[$hook])
            ? ($wp_filter[$hook]->callbacks ?? [])
            : [];
        foreach (is_array($callbacks) ? $callbacks : [] as $priority) {
            foreach (is_array($priority) ? $priority : [] as $entry) {
                $callback = is_array($entry) ? ($entry['function'] ?? null) : null;
                if (is_array($callback) && is_object($callback[0] ?? null) && ($callback[1] ?? null) === $method) {
                    return true;
                }
                if ($callback instanceof Closure) {
                    try {
                        $reflection = new ReflectionFunction($callback);
                        if ($reflection->getClosureThis() !== null && $reflection->getName() === $method) {
                            return true;
                        }
                    } catch (Throwable) {
                    }
                }
            }
        }
        return false;
    }

    /** @return list<string> */
    private function restRoutes(): array
    {
        if (!function_exists('rest_get_server')) {
            return [];
        }
        try {
            $server = rest_get_server();
            $routes = is_object($server) && method_exists($server, 'get_routes') ? $server->get_routes() : [];
            $paths = array_values(array_filter(array_keys(is_array($routes) ? $routes : []), 'is_string'));
            sort($paths, SORT_STRING);
            return $paths;
        } catch (Throwable) {
            return [];
        }
    }
}
