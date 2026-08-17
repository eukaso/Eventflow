<?php

namespace EventFlow\Application\Provider;

use DateTimeImmutable;

interface ProviderCircuitBreaker
{
    public function assertAvailable(string $provider, DateTimeImmutable $now): void;
    public function recordSuccess(string $provider): void;
    public function recordFailure(string $provider, DateTimeImmutable $now): void;
}
