<?php

namespace EventFlow\Tests\Unit\Presentation\Api;

use DateTimeImmutable;
use EventFlow\Application\Authorization\PrincipalContext;
use EventFlow\Application\Error\{RequestId, RequestIdFactory};
use EventFlow\Application\Observability\{DiagnosticBundle, DiagnosticExport};
use EventFlow\Application\Persistence\EventScope;
use EventFlow\Application\Security\SecureRandom;
use EventFlow\Presentation\Api\{AuthenticatedPrincipalResolver, AuthenticatedRequestContextFactory, DiagnosticController, DiagnosticPresenter, DiagnosticRequestMapper, DiagnosticRouteRegistrar, RequestInputException, RestRequest, RestRouteRegistry};
use PHPUnit\Framework\TestCase;

final class DiagnosticControllerTest extends TestCase
{
    public function testRouteIsAuthenticatedEventScopedAndReadOnly(): void
    {
        $routes = new DiagnosticTestRoutes();
        (new DiagnosticRouteRegistrar($this->controller(new DiagnosticTestPort())))->register($routes);
        self::assertSame(['GET eventflow/v1/events/(?P<event_id>\d+)/diagnostics'], $routes->registered);
    }

    public function testExportPreservesSanitizedSectionsAndUsesPrivateNoStoreResponse(): void
    {
        $port = new DiagnosticTestPort();
        $response = $this->controller($port)->export(new RestRequest(routeParameters:['event_id'=>'9']));

        self::assertSame(9, $port->eventId);
        self::assertSame('[REDACTED]', $response->body()['data']['sections']['runtime']['database_password']);
        self::assertSame(['status'=>'unavailable','code'=>'diagnostic_source_failed'], $response->body()['data']['sections']['failing']);
        self::assertSame('private, no-store, max-age=0', $response->headers()['Cache-Control']);
        self::assertSame('nosniff', $response->headers()['X-Content-Type-Options']);
        self::assertSame($response->body()['request_id'], $response->headers()['X-Request-ID']);
    }

    public function testQueryParametersAreRejected(): void
    {
        $this->expectException(RequestInputException::class);
        $this->controller(new DiagnosticTestPort())->export(new RestRequest(
            routeParameters:['event_id'=>'9'], queryParameters:['raw_logs'=>'true'],
        ));
    }

    private function controller(DiagnosticTestPort $port): DiagnosticController
    {
        return new DiagnosticController(
            $port,
            new AuthenticatedRequestContextFactory(new DiagnosticTestPrincipal(), new RequestIdFactory(new DiagnosticTestRandom())),
            new DiagnosticRequestMapper(),
            new DiagnosticPresenter(),
        );
    }
}

final class DiagnosticTestPort implements DiagnosticExport
{
    public ?int $eventId=null;
    public function export(PrincipalContext $principal,EventScope $scope,RequestId $requestId):DiagnosticBundle{$this->eventId=$scope->eventId;return new DiagnosticBundle($requestId->value,$scope->eventId,new DateTimeImmutable('2026-08-20T12:00:00-06:00'),['runtime'=>['database_password'=>'[REDACTED]','application_version'=>'1.0.0'],'failing'=>['status'=>'unavailable','code'=>'diagnostic_source_failed']]);}
}
final readonly class DiagnosticTestPrincipal implements AuthenticatedPrincipalResolver{public function resolve(RestRequest$request):PrincipalContext{return PrincipalContext::wordpressUser(7);}}
final readonly class DiagnosticTestRandom implements SecureRandom{public function hex(int$bytes):string{return str_repeat('6',$bytes*2);}}
final class DiagnosticTestRoutes implements RestRouteRegistry
{
    /** @var list<string> */ public array$registered=[];
    public function registerPublicGet(string$n,string$r,callable$h):void{} public function registerPublicPost(string$n,string$r,callable$h):void{} public function registerPublicPut(string$n,string$r,callable$h):void{}
    public function registerAuthenticatedGet(string$n,string$r,callable$h):void{$this->registered[]='GET '.$n.$r;} public function registerAuthenticatedPost(string$n,string$r,callable$h):void{} public function registerAuthenticatedPatch(string$n,string$r,callable$h):void{}
}
