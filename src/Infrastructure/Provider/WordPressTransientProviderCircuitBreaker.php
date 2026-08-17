<?php

namespace EventFlow\Infrastructure\Provider;

use DateTimeImmutable;
use EventFlow\Application\Provider\{ProviderCircuitBreaker, ProviderException};

final class WordPressTransientProviderCircuitBreaker implements ProviderCircuitBreaker
{
    /** @var array<string, array{failures:int,open_until:int}> */
    private array $fallback = [];

    public function __construct(private readonly int $threshold = 3, private readonly int $openSeconds = 60)
    {
        if ($threshold < 1 || $threshold > 20 || $openSeconds < 1 || $openSeconds > 3600) {
            throw new ProviderException('provider_circuit_policy_invalid');
        }
    }

    public function assertAvailable(string $provider, DateTimeImmutable $now): void
    {
        $state = $this->read($provider);
        if ($state['open_until'] > $now->getTimestamp()) {
            throw new ProviderException('provider_circuit_open');
        }
    }

    public function recordSuccess(string $provider): void
    {
        unset($this->fallback[$provider]);
        if (function_exists('delete_transient')) {
            delete_transient($this->key($provider));
        }
    }

    public function recordFailure(string $provider, DateTimeImmutable $now): void
    {
        $state = $this->read($provider);
        $failures = min($this->threshold, $state['failures'] + 1);
        $openUntil = $failures >= $this->threshold ? $now->getTimestamp() + $this->openSeconds : 0;
        $next = ['failures' => $failures, 'open_until' => $openUntil];
        $this->fallback[$provider] = $next;
        if (function_exists('set_transient')) {
            set_transient($this->key($provider), $next, max($this->openSeconds, 300));
        }
    }

    /** @return array{failures:int,open_until:int} */
    private function read(string $provider): array
    {
        $state = function_exists('get_transient') ? get_transient($this->key($provider)) : ($this->fallback[$provider] ?? false);
        if (!is_array($state) || !isset($state['failures'], $state['open_until'])) {
            return ['failures' => 0, 'open_until' => 0];
        }
        return ['failures' => max(0, (int) $state['failures']), 'open_until' => max(0, (int) $state['open_until'])];
    }

    private function key(string $provider): string
    {
        return 'eventflow_cb_' . substr(hash('sha256', $provider), 0, 32);
    }
}
