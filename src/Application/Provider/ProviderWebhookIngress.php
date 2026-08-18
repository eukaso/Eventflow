<?php

namespace EventFlow\Application\Provider;

interface ProviderWebhookIngress
{
    /** @param array<string, string> $headers */
    public function ingest(string $provider, array $headers, string $rawBody): int;
}
