<?php

namespace EventFlow\Tests\Unit\Infrastructure\Deployment;

use EventFlow\Infrastructure\Deployment\WordPressStagingEnvironmentProbe;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;

final class WordPressStagingEnvironmentProbeTest extends TestCase
{
    protected function tearDown(): void
    {
        unset($GLOBALS['wp_filter']);
    }

    #[DataProvider('serverProvider')]
    public function testDatabaseIdentityHandlesNativeAndCompatibilityPrefixedVersions(
        string $server,
        string $product,
        string $version,
    ): void {
        $database = new class($server) {
            public function __construct(private readonly string $server)
            {
            }

            public function db_server_info(): string
            {
                return $this->server;
            }
        };
        $method = new ReflectionMethod(WordPressStagingEnvironmentProbe::class, 'databaseIdentity');

        self::assertSame([$product, $version], $method->invoke(new WordPressStagingEnvironmentProbe(), $database));
    }

    /** @return iterable<string,array{string,string,string}> */
    public static function serverProvider(): iterable
    {
        yield 'MySQL' => ['8.0.41', 'mysql', '8.0.41'];
        yield 'MariaDB' => ['10.11.11-MariaDB', 'mariadb', '10.11.11'];
        yield 'MariaDB compatibility prefix' => ['5.5.5-10.11.11-MariaDB', 'mariadb', '10.11.11'];
    }

    public function testHookProbeRecognizesFirstClassMethodCallbacks(): void
    {
        $owner = new class {
            public function registerMenu(): void
            {
            }
        };
        $GLOBALS['wp_filter']['admin_menu'] = (object) [
            'callbacks' => [10 => ['eventflow' => ['function' => $owner->registerMenu(...)]]],
        ];
        $method = new ReflectionMethod(WordPressStagingEnvironmentProbe::class, 'hookOwnsMethod');

        self::assertTrue($method->invoke(new WordPressStagingEnvironmentProbe(), 'admin_menu', 'registerMenu'));
        self::assertFalse($method->invoke(new WordPressStagingEnvironmentProbe(), 'admin_menu', 'otherMethod'));
    }
}
