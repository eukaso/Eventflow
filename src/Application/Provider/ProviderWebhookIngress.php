<?php

namespace EventFlow\Application\Provider;

interface ProviderWebhookIngress
{
    /** @param array<string, string> $headers @param array<string,string> $context */
    public function ingest(string $provider, array $headers, string $rawBody, array $context = []): int;
}
