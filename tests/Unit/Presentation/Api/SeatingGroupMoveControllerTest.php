<?php

namespace EventFlow\Tests\Unit\Presentation\Api;

use EventFlow\Application\Authorization\PrincipalContext;
use EventFlow\Application\Error\RequestIdFactory;
use EventFlow\Application\Idempotency\{IdempotencyOutcome, IdempotencyResultReference};
use EventFlow\Application\Persistence\EventScope;
use EventFlow\Application\Seating\{SeatingAssignment, SeatingGroupMove, SeatingGroupMoveMember, SeatingGroupMoves};
use EventFlow\Application\Security\SecureRandom;
use EventFlow\Presentation\Api\{AuthenticatedPrincipalResolver, AuthenticatedRequestContextFactory, RequestInputException, RestRequest, RestRouteRegistry, SeatingGroupMoveController, SeatingGroupMovePresenter, SeatingGroupMoveRequestMapper, SeatingGroupMoveRouteRegistrar};
use PHPUnit\Framework\TestCase;

final class SeatingGroupMoveControllerTest extends TestCase
{
    public function testRegistrarExposesOneAuthenticatedGroupMoveRoute(): void
    {
        $routes = new SeatingGroupMoveMemoryRoutes();
        (new SeatingGroupMoveRouteRegistrar($this->controller(new SeatingGroupMovePort())))->register($routes);
        self::assertSame(['POST eventflow/v1/events/(?P<event_id>\d+)/seating-groups/(?P<group_id>\d+)/move'], $routes->registered);
    }

    public function testMoveRequiresDualPreconditionsAndReturnsControlledConcreteResult(): void
    {
        $port = new SeatingGroupMovePort();
        $response = $this->controller($port)->move($this->request([
            ['attendee_id' => 7, 'seat_id' => 51, 'expected_assignment_id' => 70],
            ['attendee_id' => 9, 'seat_id' => null, 'expected_assignment_id' => null],
        ]));

        self::assertSame(44, $port->scope?->eventId);
        self::assertSame(6, $port->groupId);
        self::assertSame(5, $port->tableId);
        self::assertSame(4, $port->revision);
        self::assertSame('group-move-001', $port->key);
        self::assertSame([7, 9], array_map(static fn (SeatingGroupMoveMember $member): int => $member->attendeeId, $port->members));
        self::assertSame(200, $response->status());
        self::assertSame(51, $response->body()['data']['assignments'][0]['seat_id']);
        self::assertSame('/wp-json/eventflow/v1/events/44/seating-groups/6', $response->headers()['Location']);
        self::assertSame('no-store, max-age=0', $response->headers()['Cache-Control']);
        self::assertMatchesRegularExpression('/^"[a-f0-9]{64}"$/', $response->headers()['ETag']);
    }

    public function testOverrideRequiresBooleanAndNonEmptyReason(): void
    {
        $port = new SeatingGroupMovePort();
        $response = $this->controller($port)->move($this->request([
            ['attendee_id' => 7, 'seat_id' => 51, 'expected_assignment_id' => 70],
            ['attendee_id' => 9, 'seat_id' => null, 'expected_assignment_id' => null],
        ], ['override_required_groups' => true, 'override_reason' => ' Host approved split ']));
        self::assertTrue($port->override);
        self::assertSame('Host approved split', $port->reason);
        self::assertTrue($response->body()['data']['required_group_override']);

        foreach ([
            ['override_required_groups' => 1, 'override_reason' => 'reason'],
            ['override_required_groups' => true],
            ['override_reason' => 'unused'],
        ] as $extra) {
            try { $this->controller(new SeatingGroupMovePort())->move($this->request([['attendee_id'=>7,'seat_id'=>51,'expected_assignment_id'=>70]], $extra)); self::fail('Expected override validation failure.'); }
            catch (RequestInputException $failure) { self::assertSame('validation_failed', $failure->safeCode); }
        }
    }

    public function testMalformedShapesRoutesAndMissingPreconditionsFailBeforePort(): void
    {
        $port = new SeatingGroupMovePort();
        $validMember = ['attendee_id'=>7,'seat_id'=>51,'expected_assignment_id'=>70];
        $requests = [
            new RestRequest(['If-Match'=>'4'], ['table_id'=>5,'members'=>[$validMember]], ['event_id'=>'44','group_id'=>'6']),
            new RestRequest(['Idempotency-Key'=>'key'], ['table_id'=>5,'members'=>[$validMember]], ['event_id'=>'44','group_id'=>'6']),
            new RestRequest(['If-Match'=>'4','Idempotency-Key'=>'key'], ['table_id'=>5,'members'=>[['attendee_id'=>7,'seat_id'=>51]]], ['event_id'=>'44','group_id'=>'6']),
            new RestRequest(['If-Match'=>'4','Idempotency-Key'=>'key'], ['table_id'=>5,'members'=>[$validMember],'force'=>true], ['event_id'=>'44','group_id'=>'6']),
            new RestRequest(['If-Match'=>'4','Idempotency-Key'=>'key'], ['table_id'=>5,'members'=>[$validMember]], ['event_id'=>'44','group_id'=>'../6']),
        ];
        foreach ($requests as $request) {
            try { $this->controller($port)->move($request); self::fail('Expected boundary failure.'); }
            catch (RequestInputException $failure) { self::assertContains($failure->safeCode, ['precondition_required', 'resource_not_found', 'validation_failed']); }
        }
        self::assertSame(0, $port->calls);
    }

    /** @param list<array<string, mixed>> $members @param array<string, mixed> $extra */
    private function request(array $members, array $extra = []): RestRequest
    {
        return new RestRequest(
            ['If-Match' => '4', 'Idempotency-Key' => 'group-move-001'],
            ['table_id' => 5, 'members' => $members, ...$extra],
            ['event_id' => '44', 'group_id' => '6'],
        );
    }

    private function controller(SeatingGroupMoves $port): SeatingGroupMoveController
    {
        return new SeatingGroupMoveController($port, new AuthenticatedRequestContextFactory(new SeatingGroupMovePrincipalResolver(), new RequestIdFactory(new SeatingGroupMoveRandom())), new SeatingGroupMoveRequestMapper(), new SeatingGroupMovePresenter());
    }
}

final class SeatingGroupMoveMemoryRoutes implements RestRouteRegistry
{
    public array $registered=[];
    public function registerPublicGet(string $namespace,string $route,callable $handler):void{}
    public function registerPublicPost(string $namespace,string $route,callable $handler):void{}
    public function registerPublicPut(string $namespace,string $route,callable $handler):void{}
    public function registerAuthenticatedGet(string $namespace,string $route,callable $handler):void{}
    public function registerAuthenticatedPost(string $namespace,string $route,callable $handler):void{$this->registered[]='POST '.$namespace.$route;}
    public function registerAuthenticatedPatch(string $namespace,string $route,callable $handler):void{}
}
final readonly class SeatingGroupMovePrincipalResolver implements AuthenticatedPrincipalResolver{public function resolve(RestRequest $request):PrincipalContext{return PrincipalContext::wordpressUser(7);}}
final readonly class SeatingGroupMoveRandom implements SecureRandom{public function hex(int $bytes):string{return str_repeat('6',$bytes*2);}}

final class SeatingGroupMovePort implements SeatingGroupMoves
{
    public int $calls=0; public ?EventScope $scope=null; public ?int $groupId=null; public ?int $tableId=null; public ?int $revision=null; public array $members=[]; public bool $override=false; public ?string $reason=null; public ?string $key=null;
    public function moveGroup(PrincipalContext $principal,EventScope $scope,int $groupId,int $tableId,int $expectedGroupRevision,array $members,bool $overrideRequiredGroups,?string $overrideReason,string $idempotencyKey):IdempotencyOutcome
    {
        $this->calls++; $this->scope=$scope; $this->groupId=$groupId; $this->tableId=$tableId; $this->revision=$expectedGroupRevision; $this->members=$members; $this->override=$overrideRequiredGroups; $this->reason=$overrideReason; $this->key=$idempotencyKey;
        $assignments=[new SeatingAssignment(71,7,5,51,'manual',$overrideRequiredGroups,$overrideReason),new SeatingAssignment(72,9,5,null,'manual',$overrideRequiredGroups,$overrideReason)];
        return new IdempotencyOutcome(false,new IdempotencyResultReference('seating_group',6,200),new SeatingGroupMove(6,5,$assignments,$overrideRequiredGroups,$overrideReason));
    }
}
