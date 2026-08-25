<?php

namespace EventFlow\Presentation\WordPress;

use EventFlow\Application\Error\RetryAfterDetails;
use EventFlow\Presentation\Api\{PublicBootstrapRateLimiter, RequestInputException};
use RuntimeException;

final readonly class WordPressPublicBootstrapRateLimiter implements PublicBootstrapRateLimiter
{
    public function __construct(
        private int $clientLimit = 10,
        private int $credentialLimit = 5,
        private int $windowSeconds = 300,
    ) {
        if ($clientLimit < 1 || $credentialLimit < 1 || $windowSeconds < 1 || $windowSeconds > 86400) {
            throw new \InvalidArgumentException('invalid_public_bootstrap_rate_limit');
        }
    }

    public function consume(?string $clientAddress, string $credentialFingerprint): void
    {
        if (!function_exists('get_transient') || !function_exists('set_transient')) {
            throw new RuntimeException('wordpress_rate_limiter_unavailable');
        }
        if (!preg_match('/^[a-f0-9]{64}$/', $credentialFingerprint)) {
            throw new RequestInputException('validation_failed');
        }
        $client = $clientAddress ?? 'unknown';
        $this->increment('eventflow_guest_ip_' . hash('sha256', $client), $this->clientLimit);
        $this->increment('eventflow_guest_credential_' . $credentialFingerprint, $this->credentialLimit);
    }

    private function increment(string $key, int $limit): void
    {
        $current = get_transient($key);
        $attempts = is_int($current) ? $current : 0;
        if ($attempts >= $limit) {
            throw new RequestInputException('rate_limit_exceeded', new RetryAfterDetails($this->windowSeconds));
        }
        $next = $attempts + 1;
        if (set_transient($key, $next, $this->windowSeconds) === false && get_transient($key) !== $next) {
            throw new RuntimeException('wordpress_rate_limiter_write_failed');
        }
    }
}
