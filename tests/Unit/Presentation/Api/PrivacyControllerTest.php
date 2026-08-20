<?php

namespace EventFlow\Tests\Unit\Presentation\Api;

use DateTimeImmutable;
use EventFlow\Application\Authorization\PrincipalContext;
use EventFlow\Application\Error\RequestIdFactory;
use EventFlow\Application\Idempotency\IdempotencyOutcome;
use EventFlow\Application\Idempotency\IdempotencyResultReference;
use EventFlow\Application\Persistence\EventScope;
use EventFlow\Application\Privacy\PrivacyAccess;
use EventFlow\Application\Privacy\PrivacyActionPage;
use EventFlow\Application\Privacy\PrivacyActionRecord;
use EventFlow\Application\Privacy\PrivacyCommands;
use EventFlow\Application\Privacy\RetentionHoldPage;
use EventFlow\Application\Privacy\RetentionHoldRecord;
use EventFlow\Application\Security\SecureRandom;
use EventFlow\Presentation\Api\AuthenticatedPrincipalResolver;
use EventFlow\Presentation\Api\AuthenticatedRequestContextFactory;
use EventFlow\Presentation\Api\PrivacyController;
use EventFlow\Presentation\Api\PrivacyPresenter;
use EventFlow\Presentation\Api\PrivacyRequestMapper;
use EventFlow\Presentation\Api\PrivacyRouteRegistrar;
use EventFlow\Presentation\Api\RequestInputException;
use EventFlow\Presentation\Api\RestRequest;
use EventFlow\Presentation\Api\RestRouteRegistry;
use PHPUnit\Framework\TestCase;

final class PrivacyControllerTest extends TestCase
{
    public function testRoutesCoverActionsHoldsAndExplicitCommands():void
    {
        $routes=new PrivacyTestRoutes();(new PrivacyRouteRegistrar($this->controller(new PrivacyTestPort())))->register($routes);
        self::assertSame([
            'GET eventflow/v1/events/(?P<event_id>\d+)/privacy-actions','POST eventflow/v1/events/(?P<event_id>\d+)/privacy-actions','GET eventflow/v1/events/(?P<event_id>\d+)/privacy-actions/(?P<privacy_action_id>\d+)',
            'GET eventflow/v1/events/(?P<event_id>\d+)/retention-holds','POST eventflow/v1/events/(?P<event_id>\d+)/retention-holds','GET eventflow/v1/events/(?P<event_id>\d+)/retention-holds/(?P<retention_hold_id>\d+)','POST eventflow/v1/events/(?P<event_id>\d+)/retention-holds/(?P<retention_hold_id>\d+)/release',
        ],$routes->registered);
    }

    public function testQueriesAndPrivacyRequestMapStrictly():void
    {
        $port=new PrivacyTestPort();$controller=$this->controller($port);
        $page=$controller->listActions(new RestRequest(routeParameters:['event_id'=>'10'],queryParameters:['limit'=>'25','after'=>'10','status'=>'processing','kind'=>'explicit','invitation_id'=>'44']));
        self::assertSame([25,10,'processing','explicit',44],$port->actionQuery);self::assertSame(12,$page->body()['meta']['next_after']);
        $created=$controller->createAction(new RestRequest(['Idempotency-Key'=>'privacy-create-001'],['purpose'=>'Verified request','policy_version'=>'retention-2026.1','invitation_id'=>44],['event_id'=>'10']));
        self::assertSame([44,'retention-2026.1','Verified request','privacy-create-001'],$port->actionCreation);self::assertSame(202,$created->status());self::assertStringContainsString('/privacy-actions/11',$created->headers()['Location']);
    }

    public function testHoldPlacementAndReleaseRequireIdempotencyAndEmptyReleaseBody():void
    {
        $port=new PrivacyTestPort();$controller=$this->controller($port);
        $holds=$controller->listHolds(new RestRequest(routeParameters:['event_id'=>'10'],queryParameters:['status'=>'active']));self::assertSame([50,null,'active',null],$port->holdQuery);self::assertSame('private, no-store, max-age=0',$holds->headers()['Cache-Control']);
        $placed=$controller->createHold(new RestRequest(['Idempotency-Key'=>'hold-place-001'],['policy_version'=>'legal-2026.1','reason'=>'Litigation preservation','invitation_id'=>null],['event_id'=>'10']));self::assertSame([null,'legal-2026.1','Litigation preservation','hold-place-001'],$port->holdCreation);self::assertSame(201,$placed->status());
        $released=$controller->releaseHold(new RestRequest(['Idempotency-Key'=>'hold-release-001'],[],['event_id'=>'10','retention_hold_id'=>'5']));self::assertSame([5,'hold-release-001'],$port->holdRelease);self::assertSame('released',$released->body()['data']['status']);
    }

    public function testUnknownPrivacyQueryFieldIsRejected():void
    {
        $this->expectException(RequestInputException::class);
        $this->controller(new PrivacyTestPort())->listActions(new RestRequest(routeParameters:['event_id'=>'10'],queryParameters:['unexpected'=>'value']));
    }

    private function controller(PrivacyTestPort$port):PrivacyController{return new PrivacyController($port,$port,new AuthenticatedRequestContextFactory(new PrivacyTestPrincipal(),new RequestIdFactory(new PrivacyTestRandom())),new PrivacyRequestMapper(),new PrivacyPresenter());}
}

final class PrivacyTestPort implements PrivacyAccess,PrivacyCommands
{
    public array$actionQuery=[];public array$holdQuery=[];public array$actionCreation=[];public array$holdCreation=[];public array$holdRelease=[];
    public function listActions(PrincipalContext$p,EventScope$s,int$l=50,?int$a=null,?string$status=null,?string$kind=null,?int$i=null):PrivacyActionPage{$this->actionQuery=[$l,$a,$status,$kind,$i];return new PrivacyActionPage([$this->action()],12);}
    public function readAction(PrincipalContext$p,EventScope$s,int$id):PrivacyActionRecord{return$this->action();}
    public function listHolds(PrincipalContext$p,EventScope$s,int$l=50,?int$a=null,?string$status=null,?int$i=null):RetentionHoldPage{$this->holdQuery=[$l,$a,$status,$i];return new RetentionHoldPage([$this->hold()],6);}
    public function readHold(PrincipalContext$p,EventScope$s,int$id):RetentionHoldRecord{return$this->hold();}
    public function request(PrincipalContext$p,EventScope$s,int$i,string$v,string$purpose,string$key):IdempotencyOutcome{$this->actionCreation=[$i,$v,$purpose,$key];return new IdempotencyOutcome(false,new IdempotencyResultReference('privacy_action',11,202),$this->action());}
    public function placeHold(PrincipalContext$p,EventScope$s,?int$i,string$v,string$r,string$key):IdempotencyOutcome{$this->holdCreation=[$i,$v,$r,$key];return new IdempotencyOutcome(false,new IdempotencyResultReference('retention_hold',5,201),$this->hold());}
    public function releaseHold(PrincipalContext$p,EventScope$s,int$id,string$key):IdempotencyOutcome{$this->holdRelease=[$id,$key];return new IdempotencyOutcome(false,new IdempotencyResultReference('retention_hold',5,200),$this->hold('released'));}
    private function action():PrivacyActionRecord{$now=new DateTimeImmutable('2026-08-19T12:00:00Z');return new PrivacyActionRecord(11,new EventScope(10),44,'explicit','retention-2026.1','Verified request','processing','pii_minimized',requestedAt:$now);}
    private function hold(string$status='active'):RetentionHoldRecord{$now=new DateTimeImmutable('2026-08-19T12:00:00Z');return new RetentionHoldRecord(5,new EventScope(10),null,'legal-2026.1','Litigation preservation',$status,7,$status==='released'?7:null,$now,$status==='released'?$now->modify('+1 hour'):null);}
}
final readonly class PrivacyTestPrincipal implements AuthenticatedPrincipalResolver{public function resolve(RestRequest$r):PrincipalContext{return PrincipalContext::wordpressUser(7);}}
final readonly class PrivacyTestRandom implements SecureRandom{public function hex(int$b):string{return str_repeat('9',$b*2);}}
final class PrivacyTestRoutes implements RestRouteRegistry{public array$registered=[];public function registerPublicGet(string$n,string$r,callable$h):void{}public function registerPublicPost(string$n,string$r,callable$h):void{}public function registerPublicPut(string$n,string$r,callable$h):void{}public function registerAuthenticatedGet(string$n,string$r,callable$h):void{$this->registered[]='GET '.$n.$r;}public function registerAuthenticatedPost(string$n,string$r,callable$h):void{$this->registered[]='POST '.$n.$r;}public function registerAuthenticatedPatch(string$n,string$r,callable$h):void{}}
