<?php

namespace EventFlow\Tests\Unit\Infrastructure\Job;

use EventFlow\Application\Authorization\PrincipalContext;
use EventFlow\Application\Job\JobExecutionContext;
use EventFlow\Application\Job\JobExecutionException;
use EventFlow\Infrastructure\Job\OperationsProbeJobHandler;
use PHPUnit\Framework\TestCase;

final class OperationsProbeJobHandlerTest extends TestCase
{
    public function testHeartbeatProbeCompletesAndHeartbeats(): void
    {
        $heartbeats = 0;
        (new OperationsProbeJobHandler())->handle(new JobExecutionContext(
            1, null, PrincipalContext::backgroundJob(1, null, []), ['mode' => 'heartbeat'], 1,
            function () use (&$heartbeats): void { $heartbeats++; },
        ));
        self::assertSame(1, $heartbeats);
    }

    public function testRetryProbeFailsOnlyOnFirstAttempt(): void
    {
        $handler = new OperationsProbeJobHandler();
        $context = static fn (int $attempt): JobExecutionContext => new JobExecutionContext(
            1, null, PrincipalContext::backgroundJob(1, null, []), ['mode' => 'retry_once'], $attempt, static function (): void {},
        );
        try {
            $handler->handle($context(1));
            self::fail('Expected retryable failure.');
        } catch (JobExecutionException $failure) {
            self::assertTrue($failure->retryable);
            self::assertSame('operations_probe_retry', $failure->safeCode);
        }
        $handler->handle($context(2));
        self::assertTrue(true);
    }
}
