<?php

namespace EventFlow\Infrastructure\Observability;

use EventFlow\Application\Observability\DiagnosticSource;
use EventFlow\Infrastructure\Config\Config;

final readonly class RuntimeDiagnosticSource implements DiagnosticSource
{
    public function __construct(private Config $config)
    {
    }

    public function identifier(): string
    {
        return 'runtime';
    }

    public function snapshot(): array
    {
        return [
            'environment' => $this->config->environment,
            'application_version' => $this->config->pluginVersion,
            'php_version' => PHP_MAJOR_VERSION . '.' . PHP_MINOR_VERSION,
            'debug_enabled' => $this->config->debugMode,
        ];
    }
}
