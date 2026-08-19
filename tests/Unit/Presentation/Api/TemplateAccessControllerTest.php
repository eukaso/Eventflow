<?php

namespace EventFlow\Tests\Unit\Presentation\Api;

use DateTimeImmutable;
use DateTimeZone;
use EventFlow\Application\Authorization\PrincipalContext;
use EventFlow\Application\Communication\{CommunicationChannel, TemplateAccess, TemplatePage, TemplateRecord, TemplateReplacement};
use EventFlow\Application\Error\RequestIdFactory;
use EventFlow\Application\Idempotency\{IdempotencyOutcome, IdempotencyResultReference};
use EventFlow\Application\Persistence\EventScope;
use EventFlow\Application\Security\SecureRandom;
use EventFlow\Presentation\Api\{AuthenticatedPrincipalResolver, AuthenticatedRequestContextFactory, RequestInputException, RestRequest, RestRouteRegistry, TemplateAccessController, TemplateAccessPresenter, TemplateAccessRequestMapper, TemplateAccessRouteRegistrar};
use PHPUnit\Framework\TestCase;

final class TemplateAccessControllerTest extends TestCase
{
    public function testRegistrarCompletesTemplateCatalogueWithoutReplacingCommands():void
    {
        $routes=new TemplateAccessMemoryRoutes();(new TemplateAccessRouteRegistrar($this->controller(new TemplateAccessPort())))->register($routes);self::assertSame([
            'GET eventflow/v1/events/(?P<event_id>\d+)/communication-templates','GET eventflow/v1/events/(?P<event_id>\d+)/communication-templates/(?P<template_id>\d+)','PATCH eventflow/v1/events/(?P<event_id>\d+)/communication-templates/(?P<template_id>\d+)','POST eventflow/v1/events/(?P<event_id>\d+)/communication-templates/(?P<template_id>\d+)/new-version','POST eventflow/v1/events/(?P<event_id>\d+)/communication-templates/(?P<template_id>\d+)/archive','POST eventflow/v1/events/(?P<event_id>\d+)/communication-templates/(?P<template_id>\d+)/preview',
        ],$routes->registered);
    }

    public function testListAndDetailExposeBoundedCursorRevisionAndUtcDates():void
    {
        $port=new TemplateAccessPort();$list=$this->controller($port)->list(new RestRequest(routeParameters:['event_id'=>'9'],queryParameters:['limit'=>'25','after'=>'10']));self::assertSame([25,10],$port->page);self::assertSame(12,$list->body()['meta']['next_after']);self::assertArrayNotHasKey('ETag',$list->headers());
        $detail=$this->controller($port)->read(new RestRequest(routeParameters:['event_id'=>'9','template_id'=>'11']));self::assertSame('reminder',$detail->body()['data']['type']);self::assertSame(4,$detail->body()['data']['revision']);self::assertSame('2026-08-20T01:00:00Z',$detail->body()['data']['updated_at']);self::assertSame('"4"',$detail->headers()['ETag']);self::assertSame('no-store, max-age=0',$detail->headers()['Cache-Control']);
    }

    public function testPatchMergesCurrentStateAndRequiresDualPreconditions():void
    {
        $port=new TemplateAccessPort();$response=$this->controller($port)->update(new RestRequest(['If-Match'=>'4','Idempotency-Key'=>'template-update-001'],['name'=>'Updated','allowed_fields'=>['guest_link']],['event_id'=>'9','template_id'=>'11']));self::assertSame('update',$port->operation);self::assertSame('Updated',$port->replacement?->name);self::assertSame('reminder',$port->replacement?->type);self::assertSame('Body {{guest_link}}',$port->replacement?->body);self::assertSame(['guest_link'],$port->replacement?->allowedFields);self::assertSame(4,$port->replacement?->expectedRevision);self::assertSame('"5"',$response->headers()['ETag']);
        foreach([new RestRequest(['If-Match'=>'4'],['name'=>'X'],['event_id'=>'9','template_id'=>'11']),new RestRequest(['Idempotency-Key'=>'key-long'],['name'=>'X'],['event_id'=>'9','template_id'=>'11'])]as$request){try{$this->controller(new TemplateAccessPort())->update($request);self::fail('Expected precondition failure.');}catch(RequestInputException $e){self::assertSame('precondition_required',$e->safeCode);}}
    }

    public function testVersionArchiveAndPreviewHavePurposeSpecificBoundaries():void
    {
        $port=new TemplateAccessPort();$headers=['If-Match'=>'4','Idempotency-Key'=>'template-transition-001'];$version=$this->controller($port)->newVersion(new RestRequest($headers,[],['event_id'=>'9','template_id'=>'11']));self::assertSame('new-version',$port->operation);self::assertSame(201,$version->status());
        $this->controller($port)->archive(new RestRequest([...$headers,'Idempotency-Key'=>'template-transition-002'],[],['event_id'=>'9','template_id'=>'11']));self::assertSame('archive',$port->operation);
        $preview=$this->controller($port)->preview(new RestRequest([],['values'=>['recipient_name'=>'Laurel']],['event_id'=>'9','template_id'=>'11']));self::assertSame(['recipient_name'=>'Laurel'],$port->values);self::assertSame('Hi Laurel',$preview->body()['data']['subject']);self::assertMatchesRegularExpression('/^"[a-f0-9]{64}"$/',$preview->headers()['ETag']);
    }

    public function testMalformedCursorPatchTransitionPreviewAndRoutesAreRejected():void
    {
        foreach([
            fn()=> $this->controller(new TemplateAccessPort())->list(new RestRequest(routeParameters:['event_id'=>'9'],queryParameters:['limit'=>'0'])),
            fn()=> $this->controller(new TemplateAccessPort())->update(new RestRequest(['If-Match'=>'4','Idempotency-Key'=>'template-invalid-001'],['admin'=>true],['event_id'=>'9','template_id'=>'11'])),
            fn()=> $this->controller(new TemplateAccessPort())->archive(new RestRequest(['If-Match'=>'4','Idempotency-Key'=>'template-invalid-002'],['force'=>true],['event_id'=>'9','template_id'=>'11'])),
            fn()=> $this->controller(new TemplateAccessPort())->preview(new RestRequest([],['values'=>['x'=>7]],['event_id'=>'9','template_id'=>'11'])),
            fn()=> $this->controller(new TemplateAccessPort())->read(new RestRequest(routeParameters:['event_id'=>'9','template_id'=>'../11'])),
        ]as$operation){try{$operation();self::fail('Expected controlled boundary failure.');}catch(RequestInputException $e){self::assertContains($e->safeCode,['validation_failed','resource_not_found']);}}
    }

    private function controller(TemplateAccess $port):TemplateAccessController{return new TemplateAccessController($port,new AuthenticatedRequestContextFactory(new TemplateAccessPrincipal(),new RequestIdFactory(new TemplateAccessRandom())),new TemplateAccessRequestMapper(),new TemplateAccessPresenter());}
}

final class TemplateAccessMemoryRoutes implements RestRouteRegistry
{
    public array $registered=[];public function registerPublicGet(string$n,string$r,callable$h):void{}public function registerPublicPost(string$n,string$r,callable$h):void{}public function registerPublicPut(string$n,string$r,callable$h):void{}public function registerAuthenticatedGet(string$n,string$r,callable$h):void{$this->registered[]='GET '.$n.$r;}public function registerAuthenticatedPost(string$n,string$r,callable$h):void{$this->registered[]='POST '.$n.$r;}public function registerAuthenticatedPatch(string$n,string$r,callable$h):void{$this->registered[]='PATCH '.$n.$r;}
}
final readonly class TemplateAccessPrincipal implements AuthenticatedPrincipalResolver{public function resolve(RestRequest$request):PrincipalContext{return PrincipalContext::wordpressUser(7);}}
final readonly class TemplateAccessRandom implements SecureRandom{public function hex(int$bytes):string{return str_repeat('9',$bytes*2);}}
final class TemplateAccessPort implements TemplateAccess
{
    public array $page=[];public string $operation='';public ?TemplateReplacement $replacement=null;public array $values=[];
    public function list(PrincipalContext$p,EventScope$s,int$limit=50,?int$afterTemplateId=null):TemplatePage{$this->page=[$limit,$afterTemplateId];return new TemplatePage([$this->record()],12);}
    public function read(PrincipalContext$p,EventScope$s,int$templateId):TemplateRecord{return $this->record();}
    public function update(PrincipalContext$p,EventScope$s,int$templateId,TemplateReplacement$r,string$key):IdempotencyOutcome{$this->operation='update';$this->replacement=$r;return $this->outcome(new TemplateRecord(11,'reminder',$r->name,CommunicationChannel::EMAIL,2,'draft',$r->subject,$r->body,$r->plainText,$r->allowedFields,$r->type,5),200);}
    public function newVersion(PrincipalContext$p,EventScope$s,int$templateId,int$expectedRevision,string$key):IdempotencyOutcome{$this->operation='new-version';return $this->outcome(new TemplateRecord(13,'reminder','Reminder',CommunicationChannel::EMAIL,3,'draft','Hi {{recipient_name}}','Body {{guest_link}}',null,['recipient_name','guest_link'],'reminder',1),201);}
    public function archive(PrincipalContext$p,EventScope$s,int$templateId,int$expectedRevision,string$key):IdempotencyOutcome{$this->operation='archive';return $this->outcome(new TemplateRecord(11,'reminder','Reminder',CommunicationChannel::EMAIL,2,'archived','Hi {{recipient_name}}','Body {{guest_link}}',null,['recipient_name','guest_link'],'reminder',5),200);}
    public function preview(PrincipalContext$p,EventScope$s,int$templateId,array$values):array{$this->values=$values;return ['template_id'=>11,'revision'=>4,'subject'=>'Hi '.($values['recipient_name']??''),'body'=>'Body','plain_text'=>null];}
    private function record():TemplateRecord{$z=new DateTimeZone('UTC');return new TemplateRecord(11,'reminder','Reminder',CommunicationChannel::EMAIL,2,'draft','Hi {{recipient_name}}','Body {{guest_link}}',null,['recipient_name','guest_link'],'reminder',4,new DateTimeImmutable('2026-08-20 00:00:00',$z),new DateTimeImmutable('2026-08-20 01:00:00',$z));}
    private function outcome(TemplateRecord$t,int$status):IdempotencyOutcome{return new IdempotencyOutcome(false,new IdempotencyResultReference('communication_template',$t->templateId,$status),$t);}
}
