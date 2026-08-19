<?php

namespace EventFlow\Tests\Unit\Presentation\Api;

use DateTimeImmutable;
use DateTimeZone;
use EventFlow\Application\Authorization\PrincipalContext;
use EventFlow\Application\Error\RequestIdFactory;
use EventFlow\Application\Idempotency\{IdempotencyOutcome, IdempotencyResultReference};
use EventFlow\Application\Persistence\EventScope;
use EventFlow\Application\Seating\{RecommendationPlan, RecommendationStatus, RecommendedPlacement, SeatingRecommendationOperations, StoredRecommendation};
use EventFlow\Application\Security\SecureRandom;
use EventFlow\Presentation\Api\{ApiResponse, AuthenticatedPrincipalResolver, AuthenticatedRequestContextFactory, RequestInputException, RestRequest, RestRouteRegistry, SeatingRecommendationController, SeatingRecommendationPresenter, SeatingRecommendationRequestMapper, SeatingRecommendationRouteRegistrar};
use PHPUnit\Framework\TestCase;

final class SeatingRecommendationControllerTest extends TestCase
{
    public function testRegistrarExposesDurableCreateReviewAndApply(): void
    {
        $routes = new SeatingRecommendationMemoryRoutes();
        (new SeatingRecommendationRouteRegistrar($this->controller(new SeatingRecommendationPort())))->register($routes);
        self::assertSame([
            'POST eventflow/v1/events/(?P<event_id>\d+)/seating/recommendations',
            'GET eventflow/v1/events/(?P<event_id>\d+)/seating/recommendations/(?P<recommendation_id>\d+)',
            'POST eventflow/v1/events/(?P<event_id>\d+)/seating/recommendations/(?P<recommendation_id>\d+)/apply',
        ], $routes->registered);
    }

    public function testGenerateRequiresIdempotencyAndReturnsDurableLocation(): void
    {
        $port = new SeatingRecommendationPort();
        $response = $this->controller($port)->generate(new RestRequest(
            ['Idempotency-Key' => 'recommend-generate-001'], ['seed' => 'layout-v1'], ['event_id' => '44'],
        ));
        self::assertSame('layout-v1', $port->seed);
        self::assertSame('recommend-generate-001', $port->key);
        self::assertSame(201, $response->status());
        self::assertSame('/wp-json/eventflow/v1/events/44/seating/recommendations/91', $response->headers()['Location']);
        self::assertMatchesRegularExpression('/^"[a-f0-9]{64}"$/', $response->headers()['ETag']);
        self::assertSame('draft', $response->body()['data']['status']);
    }

    public function testReviewReturnsPersistedPlanWithNoStoreEtagAndUtcDates(): void
    {
        $response = $this->controller(new SeatingRecommendationPort())->get(new RestRequest(routeParameters: ['event_id' => '44', 'recommendation_id' => '91']));
        self::assertSame(7, $response->body()['data']['placements'][0]['attendee_id']);
        self::assertSame(['group_split_for_capacity'], $response->body()['data']['warnings']);
        self::assertSame('2026-08-20T00:00:00Z', $response->body()['data']['created_at']);
        self::assertSame('no-store, max-age=0', $response->headers()['Cache-Control']);
        self::assertMatchesRegularExpression('/^"[a-f0-9]{64}"$/', $response->headers()['ETag']);
    }

    public function testApplyRequiresEmptyBodyAndIdempotencyAndReturnsAppliedState(): void
    {
        $port = new SeatingRecommendationPort();
        $response = $this->controller($port)->apply(new RestRequest(
            ['Idempotency-Key' => 'recommend-apply-001'], [], ['event_id' => '44', 'recommendation_id' => '91'],
        ));
        self::assertSame(91, $port->appliedId);
        self::assertSame('recommend-apply-001', $port->key);
        self::assertSame('applied', $response->body()['data']['status']);
        self::assertSame('2026-08-20T00:05:00Z', $response->body()['data']['applied_at']);

        foreach ([
            new RestRequest([], [], ['event_id'=>'44','recommendation_id'=>'91']),
            new RestRequest(['Idempotency-Key'=>'recommend-apply-002'], ['force'=>true], ['event_id'=>'44','recommendation_id'=>'91']),
        ] as $invalid) {
            try { $this->controller(new SeatingRecommendationPort())->apply($invalid); self::fail('Expected input failure.'); }
            catch (RequestInputException $failure) { self::assertContains($failure->safeCode, ['precondition_required', 'validation_failed']); }
        }
    }

    public function testMalformedGenerationAndIdentifiersFailBeforePort(): void
    {
        $port = new SeatingRecommendationPort();
        foreach ([
            new RestRequest(['Idempotency-Key'=>'recommend-invalid-001'], ['seed'=>7], ['event_id'=>'44']),
            new RestRequest(['Idempotency-Key'=>'recommend-invalid-002'], ['seed'=>'x','admin'=>true], ['event_id'=>'44']),
        ] as $invalid) {
            try { $this->controller($port)->generate($invalid); self::fail('Expected validation failure.'); }
            catch (RequestInputException $failure) { self::assertSame('validation_failed', $failure->safeCode); }
        }
        try { $this->controller($port)->get(new RestRequest(routeParameters:['event_id'=>'44','recommendation_id'=>'../91'])); self::fail('Expected route failure.'); }
        catch (RequestInputException $failure) { self::assertSame('resource_not_found', $failure->safeCode); }
        self::assertSame(0, $port->calls);
    }

    private function controller(SeatingRecommendationOperations $port): SeatingRecommendationController
    {
        return new SeatingRecommendationController($port, new AuthenticatedRequestContextFactory(new SeatingRecommendationPrincipalResolver(), new RequestIdFactory(new SeatingRecommendationRandom())), new SeatingRecommendationRequestMapper(), new SeatingRecommendationPresenter());
    }
}

final class SeatingRecommendationMemoryRoutes implements RestRouteRegistry
{
    public array $registered=[];
    public function registerPublicGet(string $namespace,string $route,callable $handler):void{}
    public function registerPublicPost(string $namespace,string $route,callable $handler):void{}
    public function registerPublicPut(string $namespace,string $route,callable $handler):void{}
    public function registerAuthenticatedGet(string $namespace,string $route,callable $handler):void{$this->registered[]='GET '.$namespace.$route;}
    public function registerAuthenticatedPost(string $namespace,string $route,callable $handler):void{$this->registered[]='POST '.$namespace.$route;}
    public function registerAuthenticatedPatch(string $namespace,string $route,callable $handler):void{}
}
final readonly class SeatingRecommendationPrincipalResolver implements AuthenticatedPrincipalResolver{public function resolve(RestRequest $request):PrincipalContext{return PrincipalContext::wordpressUser(7);}}
final readonly class SeatingRecommendationRandom implements SecureRandom{public function hex(int $bytes):string{return str_repeat('5',$bytes*2);}}

final class SeatingRecommendationPort implements SeatingRecommendationOperations
{
    public int $calls=0;public ?string $seed=null;public ?string $key=null;public ?int $appliedId=null;
    public function generate(PrincipalContext $principal,EventScope $scope,string $seed,string $idempotencyKey):IdempotencyOutcome{$this->calls++;$this->seed=$seed;$this->key=$idempotencyKey;return new IdempotencyOutcome(false,new IdempotencyResultReference('seating_recommendation',91,201),$this->record(false));}
    public function get(PrincipalContext $principal,EventScope $scope,int $recommendationId):StoredRecommendation{$this->calls++;return $this->record(false);}
    public function apply(PrincipalContext $principal,EventScope $scope,int $recommendationId,string $idempotencyKey):IdempotencyOutcome{$this->calls++;$this->appliedId=$recommendationId;$this->key=$idempotencyKey;return new IdempotencyOutcome(false,new IdempotencyResultReference('seating_recommendation',91,200),$this->record(true));}
    private function record(bool $applied):StoredRecommendation{$zone=new DateTimeZone('UTC');$plan=new RecommendationPlan(str_repeat('a',64),RecommendationPlan::ALGORITHM_VERSION,'layout-v1',[new RecommendedPlacement(7,5,51,'group:Family')],['group_split_for_capacity']);return new StoredRecommendation(91,new EventScope(44),$applied?RecommendationStatus::APPLIED:RecommendationStatus::DRAFT,$plan,new DateTimeImmutable('2026-08-20 00:00:00',$zone),$applied?new DateTimeImmutable('2026-08-20 00:05:00',$zone):null);}
}
