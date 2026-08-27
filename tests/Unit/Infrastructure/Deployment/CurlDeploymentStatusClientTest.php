<?php

namespace EventFlow\Tests\Unit\Infrastructure\Deployment;

use EventFlow\Infrastructure\Deployment\CurlDeploymentStatusClient;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;

final class CurlDeploymentStatusClientTest extends TestCase
{
    public function testRepeatedCacheControlLinesAreCombined(): void
    {
        $headers = [];
        $collector = new ReflectionMethod(CurlDeploymentStatusClient::class, 'collectHeader');

        $collector->invokeArgs(null, [&$headers, "Cache-Control: no-store, max-age=0\r\n"]);
        $collector->invokeArgs(null, [&$headers, "Cache-Control: max-age=172800\r\n"]);

        self::assertSame('no-store, max-age=0, max-age=172800', $headers['cache-control']);
    }
}
