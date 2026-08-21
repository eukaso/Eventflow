<?php
namespace EventFlow\Infrastructure\Provider;
use EventFlow\Application\Provider\ProviderException;
final readonly class WordPressProviderHttpClient implements ProviderHttpClient
{
    public function post(string $url,array $headers,string $body):ProviderHttpResponse
    {
        if(!str_starts_with($url,'https://')||!function_exists('wp_remote_post'))throw new ProviderException('provider_transport_unavailable');
        $response=wp_remote_post($url,['headers'=>$headers,'body'=>$body,'timeout'=>10,'redirection'=>0,'sslverify'=>true,'reject_unsafe_urls'=>true]);
        if(function_exists('is_wp_error')&&is_wp_error($response))throw new ProviderException('provider_transport_unknown');
        $status=(int)wp_remote_retrieve_response_code($response);$responseBody=(string)wp_remote_retrieve_body($response);
        if(strlen($responseBody)>65536)throw new ProviderException('provider_response_too_large');
        return new ProviderHttpResponse($status,$responseBody);
    }
}
