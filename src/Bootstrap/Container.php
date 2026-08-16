<?php

namespace EventFlow\Bootstrap;

use EventFlow\Infrastructure\Config\Config;

final class Container
{
    private function __construct(
        private Config $config,
        private SchemaCompatibilityChecker $schemaCompatibilityChecker,
    ) {
    }

    public static function createFoundation(Config $config): self
    {
        return new self(
            $config,
            new SchemaCompatibilityChecker(),
        );
    }

    public function config(): Config
    {
        return $this->config;
    }

    public function schemaCompatibilityChecker(): SchemaCompatibilityChecker
    {
        return $this->schemaCompatibilityChecker;
    }
}
