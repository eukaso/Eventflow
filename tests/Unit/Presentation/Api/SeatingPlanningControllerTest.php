<?php

namespace EventFlow\Tests\Unit\Presentation\Api;

use EventFlow\Application\Authorization\PrincipalContext;
use EventFlow\Application\Error\RequestIdFactory;
use EventFlow\Application\Idempotency\{IdempotencyOutcome, IdempotencyResultReference};
use EventFlow\Application\Persistence\EventScope;
use EventFlow\Application\Seating\{RecommendationPlan, RecommendedPlacement, SeatingAssignment, SeatingPlanningCommands};
use EventFlow\Application\Security\SecureRandom;
use EventFlow\Presentation\Api\{ApiResponse, AuthenticatedPrincipalResolver, AuthenticatedRequestContextFactory, RequestInputException, RestRequest, RestRouteRegistry, SeatingPlanningController, SeatingPlanningPresenter, SeatingPlanningRequestMapper, SeatingPlanningRouteRegistrar};
use PHPUnit\Framework\TestCase;

final class SeatingPlanningControllerTest extends TestCase
{
    public function testRegistrarExposesRecommendationAndManualMoveOnly(): void
    {
        $routes=new SeatingPlanningMemoryRoutes();
        (new SeatingPlanningRouteRegistrar($this->controller(new SeatingPlanningPort())))->register($routes);
        self::assertSame([
            'POST eventflow/v1/events/(?P<event_id>\d+)/seating/recommendations',
            'POST eventflow/v1/events/(?P<event_id>\d+)/attendees/(?P<attendee_id>\d+)/seating/move',
        ],$routes->registered);
    }

    public function testRecommendationMapsSeedAndReturnsDeterministicPlan(): void
    {
        $port=new SeatingPlanningPort();
        $response=$this->controller($port)->recommend(new RestRequest(
            json:['seed'=>'published-layout-v1'],routeParameters:['event_id'=>'44'],
        ));
        self::assertSame('recommend',$port->calls[0]);
        self::assertSame('published-layout-v1',$port->seed);
        self::assertSame('greedy-groups-v1',$response->body()['data']['algorithm_version']);
        self::assertSame(71,$response->body()['data']['placements'][0]['attendee_id']);
        self::assertSame('group:Family A',$response->body()['data']['placements'][0]['reason']);
    }

    public function testMoveMapsStaleGuardAndOverrideEvidence(): void
    {
        $port=new SeatingPlanningPort();
        $response=$this->controller($port)->move(new RestRequest(
            ['Idempotency-Key'=>'seating-move-001'],
            ['table_id'=>5,'seat_id'=>51,'expected_assignment_id'=>9,'override_required_group'=>true,'override_reason'=>'Host approved split'],
            ['event_id'=>'44','attendee_id'=>'71'],
        ));
        self::assertSame('move',$port->calls[0]);
        self::assertSame([71,5,51,9,true,'Host approved split'],$port->assignment);
        self::assertSame('seating-move-001',$port->key);
        self::assertSame(200,$response->status());
        self::assertTrue($response->body()['data']['group_override']);
        self::assertSame('/wp-json/eventflow/v1/events/44/attendees/71/seating',$response->headers()['Location']);
    }

    public function testNewAssignmentAllowsNullExpectedAndSeatIds(): void
    {
        $port=new SeatingPlanningPort();
        $this->controller($port)->move(new RestRequest(
            ['Idempotency-Key'=>'seating-move-002'],['table_id'=>5],['event_id'=>'44','attendee_id'=>'71'],
        ));
        self::assertSame([71,5,null,null,false,null],$port->assignment);
    }

    public function testWeakTypesUnknownFieldsAndInvalidRoutesFailBeforeService(): void
    {
        $port=new SeatingPlanningPort();
        foreach ([
            fn()=> $this->controller($port)->recommend(new RestRequest(json:['seed'=>7],routeParameters:['event_id'=>'44'])),
            fn()=> $this->controller($port)->recommend(new RestRequest(json:['seed'=>'x','admin'=>true],routeParameters:['event_id'=>'44'])),
            fn()=> $this->controller($port)->move(new RestRequest(['Idempotency-Key'=>'seating-invalid-001'],['table_id'=>'5'],['event_id'=>'44','attendee_id'=>'71'])),
            fn()=> $this->controller($port)->move(new RestRequest(['Idempotency-Key'=>'seating-invalid-002'],['table_id'=>5,'override_required_group'=>1],['event_id'=>'44','attendee_id'=>'71'])),
            fn()=> $this->controller($port)->move(new RestRequest(['Idempotency-Key'=>'seating-invalid-003'],['table_id'=>5],['event_id'=>'44','attendee_id'=>'../71'])),
            fn()=> $this->controller($port)->move(new RestRequest(['Idempotency-Key'=>'seating-invalid-004'],['table_id'=>5,'override_reason'=>'unused'],['event_id'=>'44','attendee_id'=>'71'])),
        ] as $operation){
            try{$operation();self::fail('Expected controlled input failure.');}
            catch(RequestInputException $failure){self::assertContains($failure->safeCode,['validation_failed','resource_not_found']);}
        }
        self::assertSame([],$port->calls);
    }

    private function controller(SeatingPlanningCommands $port):SeatingPlanningController
    {
        return new SeatingPlanningController(
            $port,
            new AuthenticatedRequestContextFactory(new SeatingPlanningPrincipalResolver(),new RequestIdFactory(new SeatingPlanningRandom())),
            new SeatingPlanningRequestMapper(),
            new SeatingPlanningPresenter(),
        );
    }
}

final class SeatingPlanningMemoryRoutes implements RestRouteRegistry
{
    public array $registered=[];
    public function registerPublicGet(string $namespace,string $route,callable $handler):void{}
    public function registerPublicPost(string $namespace,string $route,callable $handler):void{}
    public function registerPublicPut(string $namespace,string $route,callable $handler):void{}
    public function registerAuthenticatedGet(string $namespace,string $route,callable $handler):void{}
    public function registerAuthenticatedPost(string $namespace,string $route,callable $handler):void{$this->registered[]='POST '.$namespace.$route;}
    public function registerAuthenticatedPatch(string $namespace,string $route,callable $handler):void{}
}

final readonly class SeatingPlanningPrincipalResolver implements AuthenticatedPrincipalResolver
{
    public function resolve(RestRequest $request):PrincipalContext{return PrincipalContext::wordpressUser(7);}
}

final readonly class SeatingPlanningRandom implements SecureRandom
{
    public function hex(int $bytes):string{return str_repeat('6',$bytes*2);}
}

final class SeatingPlanningPort implements SeatingPlanningCommands
{
    public array $calls=[];
    public ?string $seed=null;
    public array $assignment=[];
    public ?string $key=null;

    public function recommend(PrincipalContext $principal,EventScope $scope,string $seed):RecommendationPlan
    {
        $this->calls[]='recommend';$this->seed=$seed;
        return new RecommendationPlan(str_repeat('a',64),RecommendationPlan::ALGORITHM_VERSION,$seed,[new RecommendedPlacement(71,5,51,'group:Family A')]);
    }

    public function assign(PrincipalContext $principal,EventScope $scope,int $attendeeId,int $tableId,?int $seatId,?int $expectedAssignmentId,bool $overrideRequiredGroup,?string $overrideReason,string $idempotencyKey):IdempotencyOutcome
    {
        $this->calls[]='move';$this->assignment=[$attendeeId,$tableId,$seatId,$expectedAssignmentId,$overrideRequiredGroup,$overrideReason];$this->key=$idempotencyKey;
        $record=new SeatingAssignment(10,$attendeeId,$tableId,$seatId,'manual',$overrideRequiredGroup,$overrideReason);
        return new IdempotencyOutcome(false,new IdempotencyResultReference('seating_assignment',10,200),$record);
    }

    public function applyRecommendation(PrincipalContext $principal,EventScope $scope,RecommendationPlan $plan,string $idempotencyKey):IdempotencyOutcome
    {
        return new IdempotencyOutcome(false,new IdempotencyResultReference('event',$scope->eventId,200),[]);
    }
}
