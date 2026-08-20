<?php

namespace EventFlow\Tests\Unit\Presentation\Api;

use DateTimeImmutable;
use EventFlow\Application\Audit\{AuditAccess, AuditAction, AuditEntityType, AuditEntry, AuditEntryPage, AuditEntrySummary, AuditIntegrityReport, AuditRecord, AuditSource};
use EventFlow\Application\Authorization\PrincipalContext;
use EventFlow\Application\Error\RequestIdFactory;
use EventFlow\Application\Persistence\EventScope;
use EventFlow\Application\Security\SecureRandom;
use EventFlow\Presentation\Api\{AuthenticatedPrincipalResolver, AuthenticatedRequestContextFactory, AuditController, AuditPresenter, AuditRequestMapper, AuditRouteRegistrar, RequestInputException, RestRequest, RestRouteRegistry};
use PHPUnit\Framework\TestCase;

final class AuditControllerTest extends TestCase
{
    public function testRoutesExposeOnlyAuthenticatedReadOperations(): void
    {
        $routes = new AuditTestRoutes();
        (new AuditRouteRegistrar($this->controller(new AuditTestPort())))->register($routes);
        self::assertSame([
            'GET eventflow/v1/events/(?P<event_id>\d+)/audit-logs',
            'GET eventflow/v1/events/(?P<event_id>\d+)/audit-logs/integrity',
            'GET eventflow/v1/events/(?P<event_id>\d+)/audit-logs/(?P<audit_log_id>\d+)',
        ], $routes->registered);
    }

    public function testListMapsStrictFiltersAndKeepsPayloadOutOfCollection(): void
    {
        $port = new AuditTestPort();
        $response = $this->controller($port)->list(new RestRequest(
            routeParameters:['event_id'=>'9'],
            queryParameters:['limit'=>'25','after'=>'40','action'=>'event.updated','entity_type'=>'event','entity_id'=>'9','actor_user_id'=>'7','source'=>'rest_api','occurred_from'=>'2026-08-18T00:00:00.123Z','occurred_until'=>'2026-08-19T00:00:00+00:00'],
        ));

        self::assertSame([25,40,'event.updated','event',9,7,'rest_api','2026-08-18T00:00:00+00:00','2026-08-19T00:00:00+00:00'], $port->query);
        self::assertSame(42, $response->body()['meta']['next_after']);
        self::assertArrayNotHasKey('before', $response->body()['data'][0]);
        self::assertArrayNotHasKey('after', $response->body()['data'][0]);
        self::assertArrayNotHasKey('actor_reference', $response->body()['data'][0]);
        self::assertSame('private, no-store, max-age=0', $response->headers()['Cache-Control']);
    }

    public function testDetailReturnsRedactedPayloadAndIntegrityReturnsNoRecords(): void
    {
        $controller = $this->controller(new AuditTestPort());
        $detail = $controller->read(new RestRequest(routeParameters:['event_id'=>'9','audit_log_id'=>'41']));
        self::assertSame(['email'=>'[redacted]'], $detail->body()['data']['before']);
        self::assertSame(str_repeat('b',64), $detail->body()['data']['record_hash']);

        $integrity = $controller->integrity(new RestRequest(routeParameters:['event_id'=>'9']));
        self::assertTrue($integrity->body()['data']['valid']);
        self::assertSame(41, $integrity->body()['data']['last_audit_log_id']);
        self::assertArrayNotHasKey('records', $integrity->body()['data']);
    }

    public function testUnknownQueryAndLenientDateAreRejected(): void
    {
        $controller = $this->controller(new AuditTestPort());
        foreach ([['unexpected'=>'value'], ['occurred_from'=>'tomorrow']] as $query) {
            try {
                $controller->list(new RestRequest(routeParameters:['event_id'=>'9'], queryParameters:$query));
                self::fail('Expected strict query rejection');
            } catch (RequestInputException $exception) {
                self::assertSame('validation_failed', $exception->safeCode);
            }
        }
        $this->expectException(RequestInputException::class);
        $controller->integrity(new RestRequest(routeParameters:['event_id'=>'9'], queryParameters:['limit'=>'1']));
    }

    private function controller(AuditTestPort $port): AuditController
    {
        return new AuditController(
            $port,
            new AuthenticatedRequestContextFactory(new AuditTestPrincipal(), new RequestIdFactory(new AuditTestRandom())),
            new AuditRequestMapper(),
            new AuditPresenter(),
        );
    }
}

final class AuditTestPort implements AuditAccess
{
    /** @var list<mixed> */ public array $query=[];
    public function list(PrincipalContext $principal,EventScope $scope,int $limit=50,?int $afterAuditLogId=null,?string $action=null,?string $entityType=null,?int $entityId=null,?int $actorUserId=null,?string $source=null,?DateTimeImmutable $occurredFrom=null,?DateTimeImmutable $occurredUntil=null):AuditEntryPage{$this->query=[$limit,$afterAuditLogId,$action,$entityType,$entityId,$actorUserId,$source,$occurredFrom?->format('c'),$occurredUntil?->format('c')];$r=$this->record();return new AuditEntryPage([new AuditEntrySummary(41,$scope,'user',7,$r->action,$r->entityType,9,'Updated',$r->source,$r->occurredAt,$r->recordHash)],42);}
    public function read(PrincipalContext $principal,EventScope $scope,int $auditLogId):AuditEntry{return new AuditEntry(41,$this->record());}
    public function verifyIntegrity(PrincipalContext $principal,EventScope $scope):AuditIntegrityReport{return new AuditIntegrityReport(true,1,41,str_repeat('b',64));}
    private function record():AuditRecord{$at=new DateTimeImmutable('2026-08-19T12:00:00Z');return new AuditRecord(new EventScope(9),'user',7,'guest:masked',AuditAction::EVENT_UPDATED,AuditEntityType::EVENT,9,'operation-id','correlation-id','Updated',['email'=>'[redacted]'],['email'=>'[redacted]'],AuditSource::REST_API,'Approved',$at,$at,1,1,null,str_repeat('b',64));}
}
final readonly class AuditTestPrincipal implements AuthenticatedPrincipalResolver{public function resolve(RestRequest$request):PrincipalContext{return PrincipalContext::wordpressUser(7);}}
final readonly class AuditTestRandom implements SecureRandom{public function hex(int$bytes):string{return str_repeat('7',$bytes*2);}}
final class AuditTestRoutes implements RestRouteRegistry
{
    /** @var list<string> */ public array$registered=[];
    public function registerPublicGet(string$n,string$r,callable$h):void{} public function registerPublicPost(string$n,string$r,callable$h):void{} public function registerPublicPut(string$n,string$r,callable$h):void{}
    public function registerAuthenticatedGet(string$n,string$r,callable$h):void{$this->registered[]='GET '.$n.$r;} public function registerAuthenticatedPost(string$n,string$r,callable$h):void{} public function registerAuthenticatedPatch(string$n,string$r,callable$h):void{}
}
