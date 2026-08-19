<?php

namespace EventFlow\Tests\Unit\Presentation\Api;

use DateTimeImmutable;
use EventFlow\Application\Authorization\PrincipalContext;
use EventFlow\Application\Error\RequestIdFactory;
use EventFlow\Application\Event\{CreateEvent, EventDraftCommands, EventDraftPatch, EventLifecycleCommands, EventPage, EventQueries, EventRecord, EventStatus};
use EventFlow\Application\Idempotency\{IdempotencyOutcome, IdempotencyResultReference};
use EventFlow\Application\Persistence\EventScope;
use EventFlow\Application\Security\SecureRandom;
use EventFlow\Presentation\Api\{ApiResponse, AuthenticatedPrincipalResolver, AuthenticatedRequestContextFactory, EventController, EventLifecycleCommand, EventPresenter, EventRequestMapper, EventRouteRegistrar, RequestInputException, RestRequest, RestRouteRegistry};
use PHPUnit\Framework\TestCase;

final class EventControllerTest extends TestCase
{
    public function testRegistrarExposesCreateAndFiveExplicitLifecycleCommands(): void
    {
        $routes = new EventMemoryRoutes();
        (new EventRouteRegistrar($this->controller(new EventCommandPort())))->register($routes);
        self::assertSame([
            'eventflow/v1/events',
            'eventflow/v1/events/(?P<event_id>\d+)/activate',
            'eventflow/v1/events/(?P<event_id>\d+)/complete',
            'eventflow/v1/events/(?P<event_id>\d+)/cancel',
            'eventflow/v1/events/(?P<event_id>\d+)/archive',
            'eventflow/v1/events/(?P<event_id>\d+)/restore',
        ], array_keys($routes->authenticatedPosts));
        self::assertSame([
            'eventflow/v1/events',
            'eventflow/v1/events/(?P<event_id>\d+)',
        ], array_keys($routes->authenticatedGets));
        self::assertSame(['eventflow/v1/events/(?P<event_id>\d+)'], array_keys($routes->authenticatedPatches));
    }

    public function testListAndDetailExposeBoundedProjectionWithRevisionEtag(): void
    {
        $port = new EventCommandPort();
        $controller = $this->controller($port);

        $list = $controller->list(new RestRequest(queryParameters: ['limit' => '2', 'after' => '40']));
        $detail = $controller->read(new RestRequest(routeParameters: ['event_id' => '44']));

        self::assertSame([2, 40], $port->pageArguments);
        self::assertSame(44, $list->body()['data'][0]['id']);
        self::assertNull($list->body()['meta']['next_after_event_id']);
        self::assertSame('"3"', $detail->headers()['ETag']);
        self::assertSame(3, $detail->body()['data']['revision']);
    }

    public function testPatchRequiresBothPreconditionsAndReturnsIncrementedEtag(): void
    {
        $port = new EventCommandPort();
        $response = $this->controller($port)->update(new RestRequest(
            ['If-Match' => '"3"', 'Idempotency-Key' => 'event-update-001'],
            ['name' => 'Updated Dinner', 'venue_id' => null],
            ['event_id' => '44'],
        ));

        self::assertSame('update', $port->calls[array_key_last($port->calls)]);
        self::assertSame(3, $port->patch?->expectedRevision);
        self::assertSame(['name' => 'Updated Dinner', 'venue_id' => null], $port->patch?->changes);
        self::assertSame('event-update-001', $port->keys[array_key_last($port->keys)]);
        self::assertSame('"4"', $response->headers()['ETag']);
        self::assertSame(4, $response->body()['data']['revision']);

        foreach ([
            ['If-Match' => '"3"'],
            ['Idempotency-Key' => 'event-update-002'],
        ] as $headers) {
            try {
                $this->controller(new EventCommandPort())->update(new RestRequest($headers, ['name' => 'X'], ['event_id' => '44']));
                self::fail('Expected required precondition failure.');
            } catch (RequestInputException $failure) {
                self::assertSame('precondition_required', $failure->safeCode);
            }
        }
    }

    public function testReadAndPatchInputsFailClosedBeforeApplicationPorts(): void
    {
        foreach ([
            fn (EventController $controller) => $controller->list(new RestRequest(queryParameters: ['limit' => '101'])),
            fn (EventController $controller) => $controller->list(new RestRequest(queryParameters: ['after' => '0'])),
            fn (EventController $controller) => $controller->read(new RestRequest(routeParameters: ['event_id' => '../44'])),
            fn (EventController $controller) => $controller->update(new RestRequest(['If-Match'=>'"3"','Idempotency-Key'=>'event-update-003'], [], ['event_id'=>'44'])),
            fn (EventController $controller) => $controller->update(new RestRequest(['If-Match'=>'"3"','Idempotency-Key'=>'event-update-004'], ['admin'=>true], ['event_id'=>'44'])),
            fn (EventController $controller) => $controller->update(new RestRequest(['If-Match'=>'"0"','Idempotency-Key'=>'event-update-005'], ['name'=>'X'], ['event_id'=>'44'])),
            fn (EventController $controller) => $controller->update(new RestRequest(['If-Match'=>'"3"','Idempotency-Key'=>'event-update-006'], ['starts_at'=>'tomorrow'], ['event_id'=>'44'])),
            fn (EventController $controller) => $controller->update(new RestRequest(['If-Match'=>'"3"','Idempotency-Key'=>'event-update-007'], ['venue_id'=>0], ['event_id'=>'44'])),
        ] as $operation) {
            $port = new EventCommandPort();
            try {
                $operation($this->controller($port));
                self::fail('Expected controlled input rejection.');
            } catch (RequestInputException $failure) {
                self::assertContains($failure->safeCode, ['validation_failed', 'resource_not_found']);
            }
            self::assertSame([], $port->calls);
        }
    }

    public function testCreateMapsValidatedInputAndReturnsNormalizedResource(): void
    {
        $port = new EventCommandPort();
        $response = $this->controller($port)->create(new RestRequest(
            ['Idempotency-Key' => 'event-create-001', 'X-Request-ID' => 'req_0123456789abcdef0123456789abcdef'],
            [
                'name' => 'Annual Dinner', 'slug' => 'annual-dinner', 'timezone' => 'America/Edmonton',
                'starts_at' => '2026-09-01T18:00:00-06:00', 'ends_at' => '2026-09-01T22:00:00-06:00', 'venue_id' => 4,
            ],
        ));
        self::assertSame('create', $port->calls[0]);
        self::assertSame('event-create-001', $port->keys[0]);
        self::assertSame(201, $response->status());
        self::assertSame('Annual Dinner', $response->body()['data']['name']);
        self::assertSame('2026-09-02T00:00:00Z', $response->body()['data']['starts_at']);
        self::assertSame('/wp-json/eventflow/v1/events/44', $response->headers()['Location']);
    }

    public function testEveryLifecycleRouteDelegatesToItsExplicitCommand(): void
    {
        $port = new EventCommandPort();
        $controller = $this->controller($port);
        foreach (EventLifecycleCommand::cases() as $command) {
            $response = $controller->transition(new RestRequest(
                ['Idempotency-Key' => 'event-command-' . $command->value],
                routeParameters: ['event_id' => '44'],
            ), $command);
            self::assertSame($command->value, $port->calls[array_key_last($port->calls)]);
            self::assertSame(44, $response->body()['data']['id']);
        }
    }

    public function testReplayReturnsStableReferenceWithoutReconstructingResource(): void
    {
        $port = new EventCommandPort(replay: true);
        $response = $this->controller($port)->transition(new RestRequest(
            ['Idempotency-Key' => 'event-command-replay'], routeParameters: ['event_id' => '44'],
        ), EventLifecycleCommand::ACTIVATE);
        self::assertSame(['type' => 'event', 'id' => 44], $response->body()['data']);
        self::assertTrue($response->body()['meta']['replayed']);
    }

    public function testUnknownFieldsInvalidDatesAndInvalidRouteIdsFailBeforeService(): void
    {
        $port = new EventCommandPort();
        foreach ([
            fn () => $this->controller($port)->create(new RestRequest(['Idempotency-Key' => 'event-invalid-001'], ['name'=>'A','slug'=>'a','timezone'=>'UTC','admin'=>true])),
            fn () => $this->controller($port)->create(new RestRequest(['Idempotency-Key' => 'event-invalid-002'], ['name'=>'A','slug'=>'a','timezone'=>'UTC','starts_at'=>'tomorrow'])),
            fn () => $this->controller($port)->create(new RestRequest(['Idempotency-Key' => 'event-invalid-004'], ['name'=>'A','slug'=>'a','timezone'=>'UTC','starts_at'=>'2026-02-31T10:00:00Z'])),
            fn () => $this->controller($port)->transition(new RestRequest(['Idempotency-Key' => 'event-invalid-003'], routeParameters:['event_id'=>'../../1']), EventLifecycleCommand::ACTIVATE),
        ] as $operation) {
            try { $operation(); self::fail('Expected controlled request failure.'); }
            catch (RequestInputException $failure) { self::assertContains($failure->safeCode, ['validation_failed', 'resource_not_found']); }
        }
        self::assertSame([], $port->calls);
    }

    private function controller(EventLifecycleCommands $port): EventController
    {
        return new EventController(
            $port,
            $port,
            $port,
            new AuthenticatedRequestContextFactory(new EventPrincipalResolver(), new RequestIdFactory(new EventControllerRandom())),
            new EventRequestMapper(),
            new EventPresenter(),
        );
    }
}

final class EventMemoryRoutes implements RestRouteRegistry
{
    public array $authenticatedPosts = [];
    public array $authenticatedGets = [];
    public array $authenticatedPatches = [];
    public function registerPublicGet(string $namespace,string $route,callable $handler):void {}
    public function registerPublicPost(string $namespace,string $route,callable $handler):void {}
    public function registerPublicPut(string $namespace,string $route,callable $handler):void {}
    public function registerAuthenticatedPost(string $namespace,string $route,callable $handler):void { $this->authenticatedPosts[$namespace.$route]=$handler; }
    public function registerAuthenticatedGet(string $namespace,string $route,callable $handler):void { $this->authenticatedGets[$namespace.$route]=$handler; }
    public function registerAuthenticatedPatch(string $namespace,string $route,callable $handler):void { $this->authenticatedPatches[$namespace.$route]=$handler; }
}

final readonly class EventPrincipalResolver implements AuthenticatedPrincipalResolver
{
    public function resolve(RestRequest $request): PrincipalContext { return PrincipalContext::wordpressUser(7); }
}

final readonly class EventControllerRandom implements SecureRandom
{
    public function hex(int $bytes): string { return str_repeat('c', $bytes * 2); }
}

final class EventCommandPort implements EventLifecycleCommands, EventQueries, EventDraftCommands
{
    public array $calls=[]; public array $keys=[];
    public array $pageArguments=[];
    public ?EventDraftPatch $patch=null;
    public function __construct(private bool $replay=false) {}
    public function create(PrincipalContext $p,CreateEvent $e,string $k):IdempotencyOutcome { $this->calls[]='create';$this->keys[]=$k;return $this->outcome(new EventScope(44),EventStatus::DRAFT,201,$e); }
    public function activate(PrincipalContext $p,EventScope $s,string $k):IdempotencyOutcome{return $this->command('activate',$s,$k,EventStatus::ACTIVE);}
    public function complete(PrincipalContext $p,EventScope $s,string $k):IdempotencyOutcome{return $this->command('complete',$s,$k,EventStatus::COMPLETED);}
    public function cancel(PrincipalContext $p,EventScope $s,string $k):IdempotencyOutcome{return $this->command('cancel',$s,$k,EventStatus::CANCELLED);}
    public function archive(PrincipalContext $p,EventScope $s,string $k):IdempotencyOutcome{return $this->command('archive',$s,$k,EventStatus::ARCHIVED);}
    public function restore(PrincipalContext $p,EventScope $s,string $k):IdempotencyOutcome{return $this->command('restore',$s,$k,EventStatus::COMPLETED);}
    public function listAccessible(PrincipalContext $principal,int $limit=50,?int $afterEventId=null):EventPage{$this->calls[]='list';$this->pageArguments=[$limit,$afterEventId];return new EventPage([$this->record(new EventScope(44))],null);}
    public function read(PrincipalContext $principal,EventScope $scope):EventRecord{$this->calls[]='read';return $this->record($scope);}
    public function updateDraft(PrincipalContext $principal,EventScope $scope,EventDraftPatch $patch,string $idempotencyKey):IdempotencyOutcome{$this->calls[]='update';$this->keys[]=$idempotencyKey;$this->patch=$patch;$record=new EventRecord($scope,'Updated Dinner','annual-dinner',EventStatus::DRAFT,'UTC',null,null,null,$patch->expectedRevision+1);return new IdempotencyOutcome(false,new IdempotencyResultReference('event',$scope->eventId,200),$record);}
    private function command(string $name,EventScope $s,string $k,EventStatus $status):IdempotencyOutcome{$this->calls[]=$name;$this->keys[]=$k;return $this->outcome($s,$status,200);}
    private function outcome(EventScope $s,EventStatus $status,int $code,?CreateEvent $create=null):IdempotencyOutcome
    {
        $reference=new IdempotencyResultReference('event',$s->eventId,$code);
        if($this->replay)return new IdempotencyOutcome(true,$reference);
        $record=new EventRecord($s,$create?->name??'Annual Dinner',$create?->slug??'annual-dinner',$status,$create?->timezone??'UTC',$create?->startsAt,$create?->endsAt,$create?->venueId,3);
        return new IdempotencyOutcome(false,$reference,$record);
    }
    private function record(EventScope $scope):EventRecord{return new EventRecord($scope,'Annual Dinner','annual-dinner',EventStatus::DRAFT,'UTC',null,null,null,3);}
}
