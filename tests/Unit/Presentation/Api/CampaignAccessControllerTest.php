<?php

namespace EventFlow\Tests\Unit\Presentation\Api;

use DateTimeImmutable;
use DateTimeZone;
use EventFlow\Application\Authorization\PrincipalContext;
use EventFlow\Application\Communication\{AudienceMode, CampaignAccess, CampaignAudiencePreview, CampaignPage, CampaignPurpose, CampaignRecord, CampaignReplacement, CommunicationChannel};
use EventFlow\Application\Error\RequestIdFactory;
use EventFlow\Application\Idempotency\{IdempotencyOutcome, IdempotencyResultReference};
use EventFlow\Application\Persistence\EventScope;
use EventFlow\Application\Security\SecureRandom;
use EventFlow\Presentation\Api\{AuthenticatedPrincipalResolver, AuthenticatedRequestContextFactory, CampaignAccessController, CampaignAccessPresenter, CampaignAccessRequestMapper, CampaignAccessRouteRegistrar, RequestInputException, RestRequest, RestRouteRegistry};
use PHPUnit\Framework\TestCase;

final class CampaignAccessControllerTest extends TestCase
{
    public function testRegistrarCompletesCampaignRouteSurface(): void
    {
        $routes=new CampaignAccessMemoryRoutes();(new CampaignAccessRouteRegistrar($this->controller(new CampaignAccessPort())))->register($routes);
        self::assertSame([
            'GET eventflow/v1/events/(?P<event_id>\d+)/campaigns',
            'GET eventflow/v1/events/(?P<event_id>\d+)/campaigns/(?P<campaign_id>\d+)',
            'PATCH eventflow/v1/events/(?P<event_id>\d+)/campaigns/(?P<campaign_id>\d+)',
            'POST eventflow/v1/events/(?P<event_id>\d+)/campaigns/(?P<campaign_id>\d+)/audience-preview',
            'POST eventflow/v1/events/(?P<event_id>\d+)/campaigns/(?P<campaign_id>\d+)/schedule',
            'POST eventflow/v1/events/(?P<event_id>\d+)/campaigns/(?P<campaign_id>\d+)/cancel',
        ],$routes->registered);
    }

    public function testListReadAndPatchExposePaginationAndRevision(): void
    {
        $port=new CampaignAccessPort();$controller=$this->controller($port);
        $list=$controller->list(new RestRequest(routeParameters:['event_id'=>'9'],queryParameters:['limit'=>'25','after'=>'10']));
        self::assertSame([25,10],$port->page);self::assertSame(52,$list->body()['meta']['next_after']);
        $read=$controller->read(new RestRequest(routeParameters:['event_id'=>'9','campaign_id'=>'51']));self::assertSame('"4"',$read->headers()['ETag']);
        $update=$controller->update(new RestRequest(['If-Match'=>'"4"','Idempotency-Key'=>'campaign-update-001'],['name'=>' Updated ','audience'=>['filter'=>'active_invitations','invitation_ids'=>[14,12]]],['event_id'=>'9','campaign_id'=>'51']));
        self::assertSame('Updated',$port->replacement?->name);self::assertSame([14,12],$port->replacement?->audience['invitation_ids']);self::assertSame(4,$port->replacement?->expectedRevision);self::assertSame('"5"',$update->headers()['ETag']);
    }

    public function testPreviewScheduleAndCancelPreservePrivacyAndPreconditions(): void
    {
        $port=new CampaignAccessPort();$controller=$this->controller($port);
        $preview=$controller->audiencePreview(new RestRequest(routeParameters:['event_id'=>'9','campaign_id'=>'51']));
        self::assertSame(2,$preview->body()['data']['recipient_count']);self::assertArrayNotHasKey('recipients',$preview->body()['data']);self::assertSame('"'.str_repeat('a',64).'"',$preview->headers()['ETag']);
        $scheduled=$controller->schedule(new RestRequest(['If-Match'=>'4','Idempotency-Key'=>'campaign-schedule-001'],['scheduled_at'=>'2026-08-22T18:30:00-06:00'],['event_id'=>'9','campaign_id'=>'51']));
        self::assertSame('2026-08-22T18:30:00-06:00',$port->scheduledAt?->format('Y-m-d\TH:i:sP'));self::assertSame('scheduled',$scheduled->body()['data']['status']);
        $cancelled=$controller->cancel(new RestRequest(['If-Match'=>'5','Idempotency-Key'=>'campaign-cancel-001'],[],['event_id'=>'9','campaign_id'=>'51']));
        self::assertSame([51,5,'campaign-cancel-001'],$port->cancelInput);self::assertSame('cancelled',$cancelled->body()['data']['status']);
    }

    public function testMalformedInputsFailBeforeMutation(): void
    {
        foreach([
            fn()=> $this->controller(new CampaignAccessPort())->list(new RestRequest(routeParameters:['event_id'=>'9'],queryParameters:['limit'=>'0'])),
            fn()=> $this->controller(new CampaignAccessPort())->update(new RestRequest(['If-Match'=>'4','Idempotency-Key'=>'campaign-bad-001'],['admin'=>true],['event_id'=>'9','campaign_id'=>'51'])),
            fn()=> $this->controller(new CampaignAccessPort())->schedule(new RestRequest(['If-Match'=>'4','Idempotency-Key'=>'campaign-bad-002'],['scheduled_at'=>'2026-02-30T00:00:00Z'],['event_id'=>'9','campaign_id'=>'51'])),
            fn()=> $this->controller(new CampaignAccessPort())->cancel(new RestRequest(['If-Match'=>'4','Idempotency-Key'=>'campaign-bad-003'],['force'=>true],['event_id'=>'9','campaign_id'=>'51'])),
            fn()=> $this->controller(new CampaignAccessPort())->read(new RestRequest(routeParameters:['event_id'=>'9','campaign_id'=>'../51'])),
        ]as$operation){try{$operation();self::fail('Expected controlled input failure.');}catch(RequestInputException$failure){self::assertContains($failure->safeCode,['validation_failed','resource_not_found']);}}
    }

    private function controller(CampaignAccess $port):CampaignAccessController{return new CampaignAccessController($port,new AuthenticatedRequestContextFactory(new CampaignAccessPrincipal(),new RequestIdFactory(new CampaignAccessRandom())),new CampaignAccessRequestMapper(),new CampaignAccessPresenter());}
}

final class CampaignAccessMemoryRoutes implements RestRouteRegistry
{
    public array$registered=[];
    public function registerPublicGet(string$n,string$r,callable$h):void{}public function registerPublicPost(string$n,string$r,callable$h):void{}public function registerPublicPut(string$n,string$r,callable$h):void{}
    public function registerAuthenticatedGet(string$n,string$r,callable$h):void{$this->registered[]='GET '.$n.$r;}public function registerAuthenticatedPost(string$n,string$r,callable$h):void{$this->registered[]='POST '.$n.$r;}public function registerAuthenticatedPatch(string$n,string$r,callable$h):void{$this->registered[]='PATCH '.$n.$r;}
}
final readonly class CampaignAccessPrincipal implements AuthenticatedPrincipalResolver{public function resolve(RestRequest$r):PrincipalContext{return PrincipalContext::wordpressUser(7);}}
final readonly class CampaignAccessRandom implements SecureRandom{public function hex(int$bytes):string{return str_repeat('8',$bytes*2);}}

final class CampaignAccessPort implements CampaignAccess
{
    public array$page=[];public ?CampaignReplacement$replacement=null;public ?DateTimeImmutable$scheduledAt=null;public array$cancelInput=[];
    public function list(PrincipalContext$p,EventScope$s,int$l=50,?int$a=null):CampaignPage{$this->page=[$l,$a];return new CampaignPage([$this->record()],52);}
    public function read(PrincipalContext$p,EventScope$s,int$id):CampaignRecord{return$this->record();}
    public function update(PrincipalContext$p,EventScope$s,int$id,CampaignReplacement$r,string$key):IdempotencyOutcome{$this->replacement=$r;return$this->outcome(new CampaignRecord(51,$r->templateId,$r->name,$r->channel,$r->purpose,$r->audienceMode,['mode'=>$r->audienceMode->value,...$r->audience],'draft',5));}
    public function audiencePreview(PrincipalContext$p,EventScope$s,int$id):CampaignAudiencePreview{return new CampaignAudiencePreview($id,2,str_repeat('a',64));}
    public function schedule(PrincipalContext$p,EventScope$s,int$id,int$r,DateTimeImmutable$at,string$key):IdempotencyOutcome{$this->scheduledAt=$at;return$this->outcome(new CampaignRecord(51,41,'Reminder',CommunicationChannel::EMAIL,CampaignPurpose::REMINDER,AudienceMode::DYNAMIC,['mode'=>'dynamic','filter'=>'active_invitations','invitation_ids'=>[]],'scheduled',5,$at));}
    public function cancel(PrincipalContext$p,EventScope$s,int$id,int$r,string$key):IdempotencyOutcome{$this->cancelInput=[$id,$r,$key];return$this->outcome(new CampaignRecord(51,41,'Reminder',CommunicationChannel::EMAIL,CampaignPurpose::REMINDER,AudienceMode::DYNAMIC,['mode'=>'dynamic','filter'=>'active_invitations','invitation_ids'=>[]],'cancelled',6,cancelledAt:new DateTimeImmutable('2026-08-20T00:00:00Z')));}
    private function record():CampaignRecord{return new CampaignRecord(51,41,'Reminder',CommunicationChannel::EMAIL,CampaignPurpose::REMINDER,AudienceMode::DYNAMIC,['mode'=>'dynamic','filter'=>'active_invitations','invitation_ids'=>[]],'draft',4,new DateTimeImmutable('2026-08-22T00:00:00Z'));}
    private function outcome(CampaignRecord$r):IdempotencyOutcome{return new IdempotencyOutcome(false,new IdempotencyResultReference('campaign',51,200),$r);}
}
