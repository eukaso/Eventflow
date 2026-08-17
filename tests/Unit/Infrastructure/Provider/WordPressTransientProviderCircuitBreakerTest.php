<?php

namespace EventFlow\Tests\Unit\Infrastructure\Provider;

use DateTimeImmutable;
use EventFlow\Application\Provider\ProviderException;
use EventFlow\Infrastructure\Provider\WordPressTransientProviderCircuitBreaker;
use PHPUnit\Framework\TestCase;

final class WordPressTransientProviderCircuitBreakerTest extends TestCase
{
    public function testThresholdOpensTemporarilyAndSuccessResetsState(): void
    {
        $breaker = new WordPressTransientProviderCircuitBreaker(2, 60);
        $now = new DateTimeImmutable('2026-08-17T18:00:00Z');
        $breaker->recordFailure('mail', $now);
        $breaker->assertAvailable('mail', $now);
        $breaker->recordFailure('mail', $now);

        try {
            $breaker->assertAvailable('mail', $now);
            self::fail('Expected open provider circuit.');
        } catch (ProviderException $exception) {
            self::assertSame('provider_circuit_open', $exception->safeCode);
        }

        $breaker->assertAvailable('mail', $now->modify('+61 seconds'));
        $breaker->recordSuccess('mail');
        $breaker->assertAvailable('mail', $now);
        self::addToAssertionCount(2);
    }
}
