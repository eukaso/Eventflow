<?php

namespace EventFlow\Infrastructure\Config;

final readonly class Config
{
    public function __construct(
        public string $environment,
        public string $pluginVersion,
        public int $expectedSchemaVersion,
        public string $logLevel,
        public bool $debugMode,
    ) {
    }
}
