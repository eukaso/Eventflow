<?php
namespace EventFlow\Infrastructure\Provider;
interface ProviderHttpClient
{
    /** @param array<string,string> $headers */
    public function post(string $url,array $headers,string $body):ProviderHttpResponse;
}
