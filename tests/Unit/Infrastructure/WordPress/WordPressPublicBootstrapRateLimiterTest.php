<?php

namespace {
    if (!function_exists('get_transient')) {
        function get_transient(string $key): mixed
        {
            return $GLOBALS['eventflow_test_transients'][$key] ?? false;
        }
    }
    if (!function_exists('set_transient')) {
        function set_transient(string $key, mixed $value, int $expiration): bool
        {
            $GLOBALS['eventflow_test_transients'][$key] = $value;
            $GLOBALS['eventflow_test_transient_expiries'][$key] = $expiration;
            return true;
        }
    }
    if (!function_exists('delete_transient')) {
        function delete_transient(string $key): bool
        {
            unset($GLOBALS['eventflow_test_transients'][$key], $GLOBALS['eventflow_test_transient_expiries'][$key]);
            return true;
        }
    }
}

namespace EventFlow\Tests\Unit\Infrastructure\WordPress {
    use EventFlow\Presentation\Api\RequestInputException;
    use EventFlow\Presentation\WordPress\WordPressPublicBootstrapRateLimiter;
    use PHPUnit\Framework\TestCase;

    final class WordPressPublicBootstrapRateLimiterTest extends TestCase
    {
        protected function setUp(): void
        {
            $GLOBALS['eventflow_test_transients'] = [];
            $GLOBALS['eventflow_test_transient_expiries'] = [];
        }

        protected function tearDown(): void
        {
            unset($GLOBALS['eventflow_test_transients'], $GLOBALS['eventflow_test_transient_expiries']);
        }

        public function testCredentialAndClientBucketsUseOnlyOneWayIdentifiers(): void
        {
            $limiter = new WordPressPublicBootstrapRateLimiter(clientLimit: 10, credentialLimit: 2, windowSeconds: 60);
            $fingerprint = hash('sha256', str_repeat('a', 64));
            $limiter->consume('203.0.113.9', $fingerprint);
            $limiter->consume('203.0.113.9', $fingerprint);

            $keys = array_keys($GLOBALS['eventflow_test_transients']);
            self::assertCount(2, $keys);
            self::assertStringNotContainsString('203.0.113.9', implode('|', $keys));
            self::assertSame([60, 60], array_values($GLOBALS['eventflow_test_transient_expiries']));

            try {
                $limiter->consume('203.0.113.9', $fingerprint);
                self::fail('Expected throttle.');
            } catch (RequestInputException $failure) {
                self::assertSame('rate_limit_exceeded', $failure->safeCode);
                self::assertSame(['retry_after_seconds' => 60], $failure->details?->toArray());
            }
        }

        public function testInvalidFingerprintFailsBeforeAnyBucketWrite(): void
        {
            try {
                (new WordPressPublicBootstrapRateLimiter())->consume('203.0.113.9', 'raw-secret');
                self::fail('Expected validation failure.');
            } catch (RequestInputException $failure) {
                self::assertSame('validation_failed', $failure->safeCode);
            }
            self::assertSame([], $GLOBALS['eventflow_test_transients']);
        }
    }
}
