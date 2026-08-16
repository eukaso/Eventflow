<?php

namespace EventFlow\Tests\Unit\Harness;

use EventFlow\Bootstrap\RuntimeRequirements;
use PHPUnit\Framework\TestCase;

final class TestHarnessTest extends TestCase
{
    public function testHarnessUsesTheDeterministicTestEnvironment(): void
    {
        self::assertSame('testing', getenv('EVENTFLOW_TEST_ENV'));
        self::assertSame('UTC', date_default_timezone_get());
    }

    public function testDeclaredRuntimeMatchesTheLanguageFeaturesAndTestRunner(): void
    {
        $composer = json_decode(
            (string) file_get_contents(dirname(__DIR__, 3) . '/composer.json'),
            true,
            flags: JSON_THROW_ON_ERROR,
        );

        self::assertSame('8.2', RuntimeRequirements::MIN_PHP_VERSION);
        self::assertSame('>=8.2', $composer['require']['php']);
        self::assertSame('^11.5', $composer['require-dev']['phpunit/phpunit']);
    }
}
