<?php
/**
 * Plugin Name: EventFlow
 * Description: Event management platform.
 * Version: 1.1.0-dev
 * Requires at least: 6.5
 * Requires PHP: 8.2
 */

defined('ABSPATH') || exit;

define('EVENTFLOW_VERSION', '1.1.0-dev');
define('EVENTFLOW_SCHEMA_VERSION', 9);
define('EVENTFLOW_PLUGIN_FILE', __FILE__);
define('EVENTFLOW_PLUGIN_DIR', __DIR__);

require_once __DIR__ . '/vendor/autoload.php';

add_action('plugins_loaded', static function (): void {
    \EventFlow\Bootstrap\ApplicationBootstrap::boot();
});
