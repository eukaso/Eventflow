<?php

namespace EventFlow\Tests\Unit\Infrastructure\Config;

use EventFlow\Infrastructure\Config\ConfigLoader;
use PHPUnit\Framework\TestCase;

final class ConfigLoaderTest extends TestCase
{
    public function testDefaultsAreSafe(): void
    {
        if (!defined('EVENTFLOW_VERSION')) {
            define('EVENTFLOW_VERSION', '0.9.0');
        }

        if (!defined('EVENTFLOW_SCHEMA_VERSION')) {
            define('EVENTFLOW_SCHEMA_VERSION', 1);
        }

        $config = (new ConfigLoader())->load();

        self::assertSame('production', $config->environment);
        self::assertSame('warning', $config->logLevel);
        self::assertFalse($config->debugMode);
    }
}
