<?php

namespace EventFlow\Tests\Unit\Presentation\Api;

use DateTimeImmutable;
use EventFlow\Application\Authorization\PrincipalContext;
use EventFlow\Application\Error\RequestIdFactory;
use EventFlow\Application\Export\ExportAccess;
use EventFlow\Application\Export\ExportArtifactReader;
use EventFlow\Application\Export\ExportDelivery;
use EventFlow\Application\Export\ExportDownloadGrant;
use EventFlow\Application\Export\ExportFormat;
use EventFlow\Application\Export\ExportPage;
use EventFlow\Application\Export\ExportRecord;
use EventFlow\Application\Export\ExportType;
use EventFlow\Application\Idempotency\IdempotencyOutcome;
use EventFlow\Application\Idempotency\IdempotencyResultReference;
use EventFlow\Application\Persistence\EventScope;
use EventFlow\Application\Security\SecureRandom;
use EventFlow\Presentation\Api\AuthenticatedPrincipalResolver;
use EventFlow\Presentation\Api\AuthenticatedRequestContextFactory;
use EventFlow\Presentation\Api\BinaryApiResponse;
use EventFlow\Presentation\Api\ExportController;
use EventFlow\Presentation\Api\ExportPresenter;
use EventFlow\Presentation\Api\ExportRequestMapper;
use EventFlow\Presentation\Api\ExportRouteRegistrar;
use EventFlow\Presentation\Api\RestRequest;
use EventFlow\Presentation\Api\RestRouteRegistry;
use PHPUnit\Framework\TestCase;

final class ExportControllerTest extends TestCase
{
    public function testRoutesCoverCreateListDetailAndDownload(): void
    {
        $routes = new ExportTestRoutes();
        (new ExportRouteRegistrar($this->controller(new ExportTestPort())))->register($routes);
        self::assertSame([
            'GET eventflow/v1/events/(?P<event_id>\d+)/exports',
            'POST eventflow/v1/events/(?P<event_id>\d+)/exports',
            'GET eventflow/v1/events/(?P<event_id>\d+)/exports/(?P<export_id>\d+)',
            'GET eventflow/v1/events/(?P<event_id>\d+)/exports/(?P<export_id>\d+)/download',
        ], $routes->registered);
    }

    public function testCreateAndListMapStrictContractsWithoutLocatorDisclosure(): void
    {
        $port = new ExportTestPort();
        $controller = $this->controller($port);
        $created = $controller->create(new RestRequest(
            ['Idempotency-Key'=>'export-create-001'],
            ['type'=>'attendees','format'=>'csv','purpose'=>'Door operations'],
            ['event_id'=>'9'],
        ));
        self::assertSame(['attendees','csv','Door operations','export-create-001'], $port->creation);
        self::assertSame(202, $created->status());
        self::assertArrayNotHasKey('artifact_locator', $created->body()['data']);

        $listed = $controller->list(new RestRequest(
            routeParameters: ['event_id'=>'9'],
            queryParameters: ['limit'=>'25','after'=>'40','status'=>'ready','contains_pii'=>'true'],
        ));
        self::assertSame([25,40,'ready',true], $port->page);
        self::assertSame(42, $listed->body()['meta']['next_after']);
    }

    public function testDownloadVerifiesThenRecordsAndReturnsTypedBinaryResponse(): void
    {
        $port = new ExportTestPort();
        $response = $this->controller($port)->download(new RestRequest(routeParameters:['event_id'=>'9','export_id'=>'41']));
        self::assertInstanceOf(BinaryApiResponse::class, $response);
        self::assertSame('verified-bytes', $response->content());
        self::assertSame('14', $response->headers()['Content-Length']);
        self::assertSame('attachment; filename="eventflow-attendees-41.csv"', $response->headers()['Content-Disposition']);
        self::assertTrue($port->recorded);
    }

    private function controller(ExportTestPort $port): ExportController
    {
        return new ExportController(
            $port,
            $port,
            new ExportTestReader(),
            new AuthenticatedRequestContextFactory(new ExportTestPrincipal(), new RequestIdFactory(new ExportTestRandom())),
            new ExportRequestMapper(),
            new ExportPresenter(),
        );
    }
}

final class ExportTestPort implements ExportDelivery, ExportAccess
{
    public array $creation = [];
    public array $page = [];
    public bool $recorded = false;

    public function request(PrincipalContext $principal, EventScope $scope, ExportType $type, ExportFormat $format, string $purpose, string $key): IdempotencyOutcome
    {
        $this->creation = [$type->value,$format->value,$purpose,$key];
        return new IdempotencyOutcome(false, new IdempotencyResultReference('export',41,202), $this->record());
    }
    public function authorizeDownload(PrincipalContext $principal, EventScope $scope, int $exportId): ExportDownloadGrant
    {
        return new ExportDownloadGrant(41,'event-9/export-41-'.str_repeat('a',32).'.csv','text/csv','eventflow-attendees-41.csv',hash('sha256','verified-bytes'),14);
    }
    public function recordDownload(PrincipalContext $principal, EventScope $scope, int $exportId): void { $this->recorded = true; }
    public function list(PrincipalContext $principal, EventScope $scope, int $limit=50, ?int $afterExportId=null, ?string $status=null, ?bool $containsPii=null): ExportPage { $this->page=[$limit,$afterExportId,$status,$containsPii];return new ExportPage([$this->record()],42); }
    public function read(PrincipalContext $principal, EventScope $scope, int $exportId): ExportRecord { return $this->record(); }
    private function record(): ExportRecord { $now=new DateTimeImmutable('2026-08-19T12:00:00Z');return new ExportRecord(41,new EventScope(9),ExportType::ATTENDEES,ExportFormat::CSV,true,'Door operations',$now,'pending',$now->modify('+1 day')); }
}

final readonly class ExportTestReader implements ExportArtifactReader { public function read(ExportDownloadGrant $grant): string { return 'verified-bytes'; } }
final readonly class ExportTestPrincipal implements AuthenticatedPrincipalResolver { public function resolve(RestRequest $request): PrincipalContext { return PrincipalContext::wordpressUser(7); } }
final readonly class ExportTestRandom implements SecureRandom { public function hex(int $bytes): string { return str_repeat('8',$bytes*2); } }
final class ExportTestRoutes implements RestRouteRegistry
{
    public array $registered=[];
    public function registerPublicGet(string $namespace,string $route,callable $handler):void{}
    public function registerPublicPost(string $namespace,string $route,callable $handler):void{}
    public function registerPublicPut(string $namespace,string $route,callable $handler):void{}
    public function registerAuthenticatedGet(string $namespace,string $route,callable $handler):void{$this->registered[]='GET '.$namespace.$route;}
    public function registerAuthenticatedPost(string $namespace,string $route,callable $handler):void{$this->registered[]='POST '.$namespace.$route;}
    public function registerAuthenticatedPatch(string $namespace,string $route,callable $handler):void{}
}
