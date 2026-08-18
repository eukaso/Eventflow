<?php

namespace EventFlow\Tests\Unit\Presentation\Api;

use EventFlow\Application\Error\RequestIdFactory;
use EventFlow\Application\Provider\ProviderWebhookIngress;
use EventFlow\Application\Security\SecureRandom;
use EventFlow\Presentation\Api\{ProviderWebhookController, ProviderWebhookPresenter, ProviderWebhookRequestMapper, ProviderWebhookRouteRegistrar, RequestInputException, RestRequest, RestRouteRegistry};
use PHPUnit\Framework\TestCase;

final class ProviderWebhookControllerTest extends TestCase
{
    public function testRegistrarExposesProviderCallbackAsPublicPost(): void
    {
        $routes = new ProviderWebhookMemoryRoutes();
        (new ProviderWebhookRouteRegistrar($this->controller(new ProviderWebhookPort())))->register($routes);
        self::assertSame([
            'POST eventflow/v1/webhooks/(?P<provider>[a-z][a-z0-9_.-]{1,63})',
        ], $routes->registered);
    }

    public function testIngressPreservesExactBodyAndNormalizedHeadersUntilAdapterAuthentication(): void
    {
        $port = new ProviderWebhookPort();
        $body = '{"id":"evt_123","data":{"status":"delivered"}}';
        $response = $this->controller($port)->ingest(new RestRequest(
            ['X-Provider-Signature'=>'sig-v1','X-Request-ID'=>'req_0123456789abcdef0123456789abcdef'],
            ['id'=>'evt_123'],
            ['provider'=>'mail.test'],
            rawBody:$body,
        ));
        self::assertSame('mail.test', $port->provider);
        self::assertSame('sig-v1', $port->headers['x-provider-signature']);
        self::assertSame($body, $port->rawBody);
        self::assertSame(202, $response->status());
        self::assertSame(['job_id'=>301,'status'=>'accepted'], $response->body()['data']);
        self::assertSame('req_0123456789abcdef0123456789abcdef', $response->headers()['X-Request-ID']);
    }

    public function testInvalidProviderFailsBeforeIngressPort(): void
    {
        $port = new ProviderWebhookPort();
        foreach (['Mail', 'a', '../mail', str_repeat('a',65)] as $provider) {
            try {
                $this->controller($port)->ingest(new RestRequest(routeParameters:['provider'=>$provider],rawBody:'payload'));
                self::fail('Expected concealed invalid provider.');
            } catch (RequestInputException $failure) {
                self::assertSame('resource_not_found',$failure->safeCode);
            }
        }
        self::assertSame(0,$port->calls);
    }

    private function controller(ProviderWebhookPort $port):ProviderWebhookController
    {
        return new ProviderWebhookController(
            $port,
            new RequestIdFactory(new ProviderWebhookRandom()),
            new ProviderWebhookRequestMapper(),
            new ProviderWebhookPresenter(),
        );
    }
}

final class ProviderWebhookMemoryRoutes implements RestRouteRegistry
{
    public array $registered=[];
    public function registerPublicGet(string $namespace,string $route,callable $handler):void{}
    public function registerPublicPost(string $namespace,string $route,callable $handler):void{$this->registered[]='POST '.$namespace.$route;}
    public function registerPublicPut(string $namespace,string $route,callable $handler):void{}
    public function registerAuthenticatedGet(string $namespace,string $route,callable $handler):void{}
    public function registerAuthenticatedPost(string $namespace,string $route,callable $handler):void{}
    public function registerAuthenticatedPatch(string $namespace,string $route,callable $handler):void{}
}

final readonly class ProviderWebhookRandom implements SecureRandom
{
    public function hex(int $bytes):string{return str_repeat('a',$bytes*2);}
}

final class ProviderWebhookPort implements ProviderWebhookIngress
{
    public int $calls=0;
    public string $provider='';
    public array $headers=[];
    public string $rawBody='';
    public function ingest(string $provider,array $headers,string $rawBody):int
    {
        $this->calls++;$this->provider=$provider;$this->headers=$headers;$this->rawBody=$rawBody;return 301;
    }
}
