<?php

namespace EventFlow\Infrastructure\Config;

final class ConfigLoader
{
    private const ENVIRONMENTS = [
        'development',
        'testing',
        'staging',
        'production',
    ];

    private const LOG_LEVELS = [
        'debug',
        'info',
        'warning',
        'error',
        'critical',
    ];

    public function load(): Config
    {
        $environment = defined('EVENTFLOW_ENV') ? (string) EVENTFLOW_ENV : 'production';
        $logLevel = defined('EVENTFLOW_LOG_LEVEL') ? (string) EVENTFLOW_LOG_LEVEL : 'warning';
        $debugMode = defined('EVENTFLOW_DEBUG') ? (bool) EVENTFLOW_DEBUG : false;

        if (!in_array($environment, self::ENVIRONMENTS, true)) {
            throw new ConfigException('invalid_environment_configuration');
        }

        if (!in_array($logLevel, self::LOG_LEVELS, true)) {
            throw new ConfigException('invalid_log_level_configuration');
        }

        return new Config(
            environment: $environment,
            pluginVersion: (string) EVENTFLOW_VERSION,
            expectedSchemaVersion: (int) EVENTFLOW_SCHEMA_VERSION,
            logLevel: $logLevel,
            debugMode: $debugMode,
        );
    }
}
