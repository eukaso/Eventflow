<?php

namespace EventFlow\Tests\Unit\Presentation\Api;

use EventFlow\Application\Authorization\PrincipalContext;
use EventFlow\Application\Error\RequestIdFactory;
use EventFlow\Application\Idempotency\{IdempotencyOutcome, IdempotencyResultReference};
use EventFlow\Application\Import\{ImportDryRun, ImportMapping, ImportValidation};
use EventFlow\Application\Persistence\EventScope;
use EventFlow\Application\Security\SecureRandom;
use EventFlow\Presentation\Api\{AuthenticatedPrincipalResolver, AuthenticatedRequestContextFactory, ImportController, ImportPresenter, ImportRequestMapper, ImportRouteRegistrar, RequestInputException, RestRequest, RestRouteRegistry};
use PHPUnit\Framework\TestCase;

final class ImportControllerTest extends TestCase
{
    public function testRegistrarExposesValidationOnly(): void
    {
        $routes=new ImportMemoryRoutes();
        (new ImportRouteRegistrar($this->controller(new ImportPort())))->register($routes);
        self::assertSame([
            'POST eventflow/v1/events/(?P<event_id>\d+)/imports/(?P<import_job_id>\d+)/validate',
        ],$routes->registered);
    }

    public function testValidationMapsClosedColumnsAndReturnsDryRunCounts(): void
    {
        $port=new ImportPort();
        $response=$this->controller($port)->validate(new RestRequest(
            ['Idempotency-Key'=>'import-validate-001'],
            ['mapping'=>['primary_name'=>' Guest Name ','primary_email'=>'Email','capacity'=>'Seats']],
            ['event_id'=>'9','import_job_id'=>'71'],
        ));
        self::assertSame(9,$port->scope?->eventId);
        self::assertSame(71,$port->jobId);
        self::assertSame(['primary_name'=>'Guest Name','primary_email'=>'Email','capacity'=>'Seats'],$port->mapping?->columns);
        self::assertSame('import-validate-001',$port->key);
        self::assertSame(200,$response->status());
        self::assertSame([
            'import_job_id'=>71,'total_rows'=>20,'ready_rows'=>17,'invalid_rows'=>2,'warning_rows'=>1,
        ],$response->body()['data']);
        self::assertSame('/wp-json/eventflow/v1/events/9/imports/71',$response->headers()['Location']);
    }

    public function testReplayReturnsStableJobReference(): void
    {
        $port=new ImportPort();$port->replay=true;
        $response=$this->controller($port)->validate(new RestRequest(
            ['Idempotency-Key'=>'import-validate-002'],['mapping'=>['primary_name'=>'Name']],['event_id'=>'9','import_job_id'=>'71'],
        ));
        self::assertTrue($response->body()['meta']['replayed']);
        self::assertSame(['type'=>'import_job','id'=>71],$response->body()['data']);
    }

    public function testInvalidMappingsUnknownFieldsWeakTypesAndRoutesFailBeforePort(): void
    {
        $port=new ImportPort();
        foreach ([
            fn()=>$this->controller($port)->validate(new RestRequest(['Idempotency-Key'=>'import-bad-001'],['mapping'=>[]],['event_id'=>'9','import_job_id'=>'71'])),
            fn()=>$this->controller($port)->validate(new RestRequest(['Idempotency-Key'=>'import-bad-002'],['mapping'=>['primary_name'=>7]],['event_id'=>'9','import_job_id'=>'71'])),
            fn()=>$this->controller($port)->validate(new RestRequest(['Idempotency-Key'=>'import-bad-003'],['mapping'=>['primary_name'=>'Name','admin'=>'yes']],['event_id'=>'9','import_job_id'=>'71'])),
            fn()=>$this->controller($port)->validate(new RestRequest(['Idempotency-Key'=>'import-bad-004'],['mapping'=>['primary_name'=>'Name'],'force'=>true],['event_id'=>'9','import_job_id'=>'71'])),
            fn()=>$this->controller($port)->validate(new RestRequest(['Idempotency-Key'=>'import-bad-005'],['mapping'=>['primary_name'=>'Name']],['event_id'=>'9','import_job_id'=>'../71'])),
        ] as $operation){
            try{$operation();self::fail('Expected controlled input failure.');}
            catch(RequestInputException $failure){self::assertContains($failure->safeCode,['validation_failed','resource_not_found']);}
        }
        self::assertSame(0,$port->calls);
    }

    private function controller(ImportPort $port):ImportController
    {
        return new ImportController($port,new AuthenticatedRequestContextFactory(new ImportPrincipalResolver(),new RequestIdFactory(new ImportRandom())),new ImportRequestMapper(),new ImportPresenter());
    }
}

final class ImportMemoryRoutes implements RestRouteRegistry
{
    public array $registered=[];
    public function registerPublicGet(string $namespace,string $route,callable $handler):void{}
    public function registerPublicPost(string $namespace,string $route,callable $handler):void{}
    public function registerPublicPut(string $namespace,string $route,callable $handler):void{}
    public function registerAuthenticatedGet(string $namespace,string $route,callable $handler):void{}
    public function registerAuthenticatedPost(string $namespace,string $route,callable $handler):void{$this->registered[]='POST '.$namespace.$route;}
    public function registerAuthenticatedPatch(string $namespace,string $route,callable $handler):void{}
}

final readonly class ImportPrincipalResolver implements AuthenticatedPrincipalResolver
{
    public function resolve(RestRequest $request):PrincipalContext{return PrincipalContext::wordpressUser(7);}
}

final readonly class ImportRandom implements SecureRandom
{
    public function hex(int $bytes):string{return str_repeat('b',$bytes*2);}
}

final class ImportPort implements ImportValidation
{
    public int $calls=0;public ?EventScope $scope=null;public int $jobId=0;public ?ImportMapping $mapping=null;public string $key='';public bool $replay=false;
    public function validate(PrincipalContext $principal,EventScope $scope,int $jobId,ImportMapping $mapping,string $idempotencyKey):IdempotencyOutcome
    {
        $this->calls++;$this->scope=$scope;$this->jobId=$jobId;$this->mapping=$mapping;$this->key=$idempotencyKey;
        return new IdempotencyOutcome($this->replay,new IdempotencyResultReference('import_job',$jobId,200),$this->replay?null:new ImportDryRun(20,17,2,1));
    }
}
