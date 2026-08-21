<?php
namespace EventFlow\Infrastructure\Provider;
final readonly class ProviderHttpResponse
{
    public function __construct(public int $statusCode,public string $body){if($statusCode<100||$statusCode>599)throw new \InvalidArgumentException('provider_http_response_invalid');}
}
