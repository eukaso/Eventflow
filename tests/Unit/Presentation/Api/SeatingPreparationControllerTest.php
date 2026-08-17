<?php

namespace EventFlow\Tests\Unit\Presentation\Api;

use EventFlow\Application\Authorization\PrincipalContext;
use EventFlow\Application\Error\RequestIdFactory;
use EventFlow\Application\Idempotency\{IdempotencyOutcome, IdempotencyResultReference};
use EventFlow\Application\Persistence\EventScope;
use EventFlow\Application\Seating\{ConfiguredTable, ConstraintLevel, SeatingGroup, SeatingPreparation, SeatingReadiness, SeatingSeat, SeatingTable};
use EventFlow\Application\Security\SecureRandom;
use EventFlow\Presentation\Api\{ApiResponse, AuthenticatedPrincipalResolver, AuthenticatedRequestContextFactory, RequestInputException, RestRequest, RestRouteRegistry, SeatingPreparationController, SeatingPreparationPresenter, SeatingPreparationRequestMapper, SeatingPreparationRouteRegistrar};
use PHPUnit\Framework\TestCase;

final class SeatingPreparationControllerTest extends TestCase
{
    public function testRegistrarExposesTwoCreatesAndReadinessOnly(): void
    {
        $routes = new SeatingPreparationMemoryRoutes();
        (new SeatingPreparationRouteRegistrar($this->controller(new SeatingPreparationPort())))->register($routes);
        self::assertSame([
            'POST eventflow/v1/events/(?P<event_id>\d+)/tables',
            'POST eventflow/v1/events/(?P<event_id>\d+)/seating-groups',
            'GET eventflow/v1/events/(?P<event_id>\d+)/seating/readiness',
        ], $routes->registered);
    }

    public function testCreateTableMapsStrictSeatInventoryAndPresentsConfiguration(): void
    {
        $port = new SeatingPreparationPort();
        $response = $this->controller($port)->createTable(new RestRequest(
            ['Idempotency-Key'=>'seating-table-001'],
            ['name'=>'Head Table','capacity'=>4,'seats'=>[['label'=>'A1','accessible'=>true],['label'=>'A2']]],
            ['event_id'=>'44'],
        ));
        self::assertSame('table', $port->calls[0]);
        self::assertSame('seating-table-001', $port->keys[0]);
        self::assertSame([['label'=>'A1','accessible'=>true],['label'=>'A2','accessible'=>false]], $port->seats);
        self::assertSame(201, $response->status());
        self::assertTrue($response->body()['data']['seats'][0]['accessible']);
        self::assertSame('/wp-json/eventflow/v1/events/44/tables/5', $response->headers()['Location']);
    }

    public function testCreateGroupMapsConstraintAndMembers(): void
    {
        $port = new SeatingPreparationPort();
        $response = $this->controller($port)->createGroup(new RestRequest(
            ['Idempotency-Key'=>'seating-group-001'],
            ['name'=>'Family A','category'=>'family','constraint_level'=>'required','priority'=>10,'attendee_ids'=>[7,8]],
            ['event_id'=>'44'],
        ));
        self::assertSame(ConstraintLevel::REQUIRED, $port->constraint);
        self::assertSame([7,8], $port->attendeeIds);
        self::assertSame('required', $response->body()['data']['constraint_level']);
        self::assertSame('/wp-json/eventflow/v1/events/44/seating-groups/6', $response->headers()['Location']);
    }

    public function testReadinessRequiresNoMutationPreconditionAndReturnsFingerprint(): void
    {
        $port = new SeatingPreparationPort();
        $response = $this->controller($port)->readiness(new RestRequest(routeParameters:['event_id'=>'44']));
        self::assertSame('readiness', $port->calls[0]);
        self::assertFalse($response->body()['data']['ready']);
        self::assertSame(['seating_tables_required'], $response->body()['data']['errors']);
        self::assertSame(str_repeat('f',64), $response->body()['data']['input_fingerprint']);
    }

    public function testWeakTypesUnknownFieldsAndInvalidRoutesFailBeforeService(): void
    {
        $port = new SeatingPreparationPort();
        foreach ([
            fn () => $this->controller($port)->createTable(new RestRequest(['Idempotency-Key'=>'seating-invalid-001'], ['name'=>'T','capacity'=>'4','seats'=>[]], ['event_id'=>'44'])),
            fn () => $this->controller($port)->createTable(new RestRequest(['Idempotency-Key'=>'seating-invalid-002'], ['name'=>'T','capacity'=>4,'seats'=>[['label'=>'A','accessible'=>1]]], ['event_id'=>'44'])),
            fn () => $this->controller($port)->createTable(new RestRequest(['Idempotency-Key'=>'seating-invalid-003'], ['name'=>'T','capacity'=>4,'seats'=>[],'admin'=>true], ['event_id'=>'44'])),
            fn () => $this->controller($port)->createGroup(new RestRequest(['Idempotency-Key'=>'seating-invalid-004'], ['name'=>'G','category'=>'family','constraint_level'=>'hard','priority'=>1,'attendee_ids'=>[1]], ['event_id'=>'44'])),
            fn () => $this->controller($port)->createGroup(new RestRequest(['Idempotency-Key'=>'seating-invalid-005'], ['name'=>'G','category'=>'family','constraint_level'=>'required','priority'=>1,'attendee_ids'=>['1']], ['event_id'=>'44'])),
            fn () => $this->controller($port)->readiness(new RestRequest(routeParameters:['event_id'=>'../44'])),
        ] as $operation) {
            try { $operation(); self::fail('Expected controlled input failure.'); }
            catch (RequestInputException $failure) { self::assertContains($failure->safeCode,['validation_failed','resource_not_found']); }
        }
        self::assertSame([], $port->calls);
    }

    private function controller(SeatingPreparation $port): SeatingPreparationController
    {
        return new SeatingPreparationController(
            $port,
            new AuthenticatedRequestContextFactory(new SeatingPreparationPrincipalResolver(),new RequestIdFactory(new SeatingPreparationRandom())),
            new SeatingPreparationRequestMapper(),
            new SeatingPreparationPresenter(),
        );
    }
}

final class SeatingPreparationMemoryRoutes implements RestRouteRegistry
{
    public array $registered=[];
    public function registerPublicGet(string $namespace,string $route,callable $handler):void {}
    public function registerPublicPost(string $namespace,string $route,callable $handler):void {}
    public function registerPublicPut(string $namespace,string $route,callable $handler):void {}
    public function registerAuthenticatedGet(string $namespace,string $route,callable $handler):void {$this->registered[]='GET '.$namespace.$route;}
    public function registerAuthenticatedPost(string $namespace,string $route,callable $handler):void {$this->registered[]='POST '.$namespace.$route;}
    public function registerAuthenticatedPatch(string $namespace,string $route,callable $handler):void {}
}

final readonly class SeatingPreparationPrincipalResolver implements AuthenticatedPrincipalResolver
{
    public function resolve(RestRequest $request):PrincipalContext{return PrincipalContext::wordpressUser(7);}
}

final readonly class SeatingPreparationRandom implements SecureRandom
{
    public function hex(int $bytes):string{return str_repeat('7',$bytes*2);}
}

final class SeatingPreparationPort implements SeatingPreparation
{
    public array $calls=[];
    public array $keys=[];
    public array $seats=[];
    public ?ConstraintLevel $constraint=null;
    public array $attendeeIds=[];

    public function createTable(PrincipalContext $principal,EventScope $scope,string $name,int $capacity,array $seats,string $idempotencyKey):IdempotencyOutcome
    {
        $this->calls[]='table';$this->keys[]=$idempotencyKey;$this->seats=$seats;
        $configured=new ConfiguredTable(new SeatingTable(5,$name,$capacity),[new SeatingSeat(51,5,$seats[0]['label'],$seats[0]['accessible']),new SeatingSeat(52,5,$seats[1]['label'],$seats[1]['accessible'])]);
        return new IdempotencyOutcome(false,new IdempotencyResultReference('seating_table',5,201),$configured);
    }

    public function createGroup(PrincipalContext $principal,EventScope $scope,string $name,string $category,ConstraintLevel $constraint,int $priority,array $attendeeIds,string $idempotencyKey):IdempotencyOutcome
    {
        $this->calls[]='group';$this->keys[]=$idempotencyKey;$this->constraint=$constraint;$this->attendeeIds=$attendeeIds;
        $group=new SeatingGroup(6,$name,$constraint,$priority,$attendeeIds);
        return new IdempotencyOutcome(false,new IdempotencyResultReference('seating_group',6,201),$group);
    }

    public function readiness(PrincipalContext $principal,EventScope $scope):SeatingReadiness
    {
        $this->calls[]='readiness';
        return new SeatingReadiness(false,['seating_tables_required'],['table_level_seating_mode'],str_repeat('f',64));
    }
}
