<?php

namespace EventFlow\Tests\Unit\Presentation\Api;

use EventFlow\Application\Authorization\PrincipalContext;
use EventFlow\Application\Error\RequestIdFactory;
use EventFlow\Application\Idempotency\{IdempotencyOutcome, IdempotencyResultReference};
use EventFlow\Application\Persistence\EventScope;
use EventFlow\Application\Seating\{ConfiguredTable, ConstraintLevel, SeatingAssignment, SeatingAttendee, SeatingGroup, SeatingGroupReplacement, SeatingResourceAccess, SeatingSeat, SeatingSeatReplacement, SeatingSnapshot, SeatingTable, SeatingTableReplacement};
use EventFlow\Application\Security\SecureRandom;
use EventFlow\Presentation\Api\{ApiResponse, AuthenticatedPrincipalResolver, AuthenticatedRequestContextFactory, RequestInputException, RestRequest, RestRouteRegistry, SeatingResourceController, SeatingResourcePresenter, SeatingResourceRequestMapper, SeatingResourceRouteRegistrar};
use PHPUnit\Framework\TestCase;

final class SeatingResourceControllerTest extends TestCase
{
    public function testRegistrarExposesScopedResourceReadsAndMutations(): void
    {
        $routes = new SeatingResourceMemoryRoutes();
        (new SeatingResourceRouteRegistrar($this->controller(new SeatingResourcePort())))->register($routes);
        self::assertSame([
            'GET eventflow/v1/events/(?P<event_id>\d+)/tables',
            'GET eventflow/v1/events/(?P<event_id>\d+)/tables/(?P<table_id>\d+)',
            'PATCH eventflow/v1/events/(?P<event_id>\d+)/tables/(?P<table_id>\d+)',
            'GET eventflow/v1/events/(?P<event_id>\d+)/tables/(?P<table_id>\d+)/seats',
            'POST eventflow/v1/events/(?P<event_id>\d+)/tables/(?P<table_id>\d+)/seats',
            'GET eventflow/v1/events/(?P<event_id>\d+)/tables/(?P<table_id>\d+)/seats/(?P<seat_id>\d+)',
            'PATCH eventflow/v1/events/(?P<event_id>\d+)/tables/(?P<table_id>\d+)/seats/(?P<seat_id>\d+)',
            'GET eventflow/v1/events/(?P<event_id>\d+)/seating-groups',
            'GET eventflow/v1/events/(?P<event_id>\d+)/seating-groups/(?P<group_id>\d+)',
            'PATCH eventflow/v1/events/(?P<event_id>\d+)/seating-groups/(?P<group_id>\d+)',
        ], $routes->registered);
    }

    public function testReadsReturnRevisionsEtagsAndNoStoreHeaders(): void
    {
        $controller = $this->controller(new SeatingResourcePort());
        $list = $controller->listTables($this->request());
        self::assertSame(3, $list->body()['data'][0]['revision']);
        self::assertSame(1, $list->body()['data'][0]['occupancy']);
        self::assertSame('Laurel Guest', $list->body()['data'][0]['assigned_attendees'][0]['attendee_name']);
        self::assertArrayNotHasKey('ETag', $list->headers());
        $table = $controller->table($this->request(['table_id' => '5']));
        self::assertSame('"3"', $table->headers()['ETag']);
        self::assertSame(8, $table->body()['data']['seats'][0]['revision']);
        self::assertSame('no-store, max-age=0', $table->headers()['Cache-Control']);
        $group = $controller->group($this->request(['group_id' => '6']));
        self::assertSame('host_defined', $group->body()['data']['source']);
        self::assertSame('"4"', $group->headers()['ETag']);
    }

    public function testPatchMergesCurrentStateAndRequiresBothMutationPreconditions(): void
    {
        $port = new SeatingResourcePort();
        $response = $this->controller($port)->updateTable($this->request(
            ['table_id' => '5'], ['capacity' => 6], ['If-Match' => '"3"', 'Idempotency-Key' => 'table-update-001'],
        ));
        self::assertSame('Head Table', $port->tableReplacement?->name);
        self::assertSame(6, $port->tableReplacement?->capacity);
        self::assertSame(3, $port->tableReplacement?->expectedRevision);
        self::assertSame('table-update-001', $port->key);
        self::assertSame('"4"', $response->headers()['ETag']);

        foreach ([
            $this->request(['table_id' => '5'], ['capacity' => 6], ['Idempotency-Key' => 'table-update-002']),
            $this->request(['table_id' => '5'], ['capacity' => 6], ['If-Match' => '"3"']),
        ] as $invalid) {
            try { $this->controller(new SeatingResourcePort())->updateTable($invalid); self::fail('Expected precondition failure.'); }
            catch (RequestInputException $failure) { self::assertSame('precondition_required', $failure->safeCode); }
        }
    }

    public function testSeatCreateAndPatchUseStrictTableScopedResources(): void
    {
        $port = new SeatingResourcePort(); $controller = $this->controller($port);
        $created = $controller->createSeat($this->request(['table_id' => '5'], ['label' => 'B', 'accessible' => true], ['Idempotency-Key' => 'seat-create-001']));
        self::assertSame('B', $port->seatLabel);
        self::assertSame('/wp-json/eventflow/v1/events/44/tables/5/seats/52', $created->headers()['Location']);
        self::assertSame('"1"', $created->headers()['ETag']);

        $updated = $controller->updateSeat($this->request(['table_id' => '5', 'seat_id' => '51'], ['label' => 'A1'], ['If-Match' => '8', 'Idempotency-Key' => 'seat-update-001']));
        self::assertTrue($port->seatReplacement?->accessible);
        self::assertSame(8, $port->seatReplacement?->expectedRevision);
        self::assertSame('"9"', $updated->headers()['ETag']);

        try { $controller->seat($this->request(['table_id' => '99', 'seat_id' => '51'])); self::fail('Expected parent scope failure.'); }
        catch (RequestInputException $failure) { self::assertSame('resource_not_found', $failure->safeCode); }
    }

    public function testGroupPatchAndMalformedBodiesFailClosed(): void
    {
        $port = new SeatingResourcePort();
        $response = $this->controller($port)->updateGroup($this->request(
            ['group_id' => '6'], ['constraint_level' => 'required', 'attendee_ids' => [9, 7]], ['If-Match' => '"4"', 'Idempotency-Key' => 'group-update-001'],
        ));
        self::assertSame(ConstraintLevel::REQUIRED, $port->groupReplacement?->constraintLevel);
        self::assertSame([9, 7], $port->groupReplacement?->attendeeIds);
        self::assertSame('"5"', $response->headers()['ETag']);

        foreach ([[], ['admin' => true], ['attendee_ids' => ['7']], ['constraint_level' => 'hard']] as $body) {
            try { $this->controller(new SeatingResourcePort())->updateGroup($this->request(['group_id' => '6'], $body, ['If-Match' => '4', 'Idempotency-Key' => 'group-invalid-001'])); self::fail('Expected validation failure.'); }
            catch (RequestInputException $failure) { self::assertSame('validation_failed', $failure->safeCode); }
        }
    }

    /** @param array<string,string> $routes @param array<string,mixed> $body @param array<string,string> $headers */
    private function request(array $routes = [], array $body = [], array $headers = []): RestRequest
    {
        return new RestRequest($headers, $body, ['event_id' => '44', ...$routes]);
    }

    private function controller(SeatingResourceAccess $port): SeatingResourceController
    {
        return new SeatingResourceController($port, new AuthenticatedRequestContextFactory(new SeatingResourcePrincipalResolver(), new RequestIdFactory(new SeatingResourceRandom())), new SeatingResourceRequestMapper(), new SeatingResourcePresenter());
    }
}

final class SeatingResourceMemoryRoutes implements RestRouteRegistry
{
    public array $registered=[];
    public function registerPublicGet(string $namespace,string $route,callable $handler):void {}
    public function registerPublicPost(string $namespace,string $route,callable $handler):void {}
    public function registerPublicPut(string $namespace,string $route,callable $handler):void {}
    public function registerAuthenticatedGet(string $namespace,string $route,callable $handler):void {$this->registered[]='GET '.$namespace.$route;}
    public function registerAuthenticatedPost(string $namespace,string $route,callable $handler):void {$this->registered[]='POST '.$namespace.$route;}
    public function registerAuthenticatedPatch(string $namespace,string $route,callable $handler):void {$this->registered[]='PATCH '.$namespace.$route;}
}

final readonly class SeatingResourcePrincipalResolver implements AuthenticatedPrincipalResolver { public function resolve(RestRequest $request):PrincipalContext{return PrincipalContext::wordpressUser(7);} }
final readonly class SeatingResourceRandom implements SecureRandom { public function hex(int $bytes):string{return str_repeat('8',$bytes*2);} }

final class SeatingResourcePort implements SeatingResourceAccess
{
    public ?SeatingTableReplacement $tableReplacement=null; public ?SeatingSeatReplacement $seatReplacement=null; public ?SeatingGroupReplacement $groupReplacement=null; public ?string $key=null; public ?string $seatLabel=null;
    public function snapshot(PrincipalContext $principal,EventScope $scope):SeatingSnapshot{return new SeatingSnapshot([new SeatingAttendee(7,'Laurel Guest')],[$this->tableRecord()],[$this->seatRecord()],[$this->groupRecord()],[new SeatingAssignment(70,7,5,51,'manual')]);}
    public function table(PrincipalContext $principal,EventScope $scope,int $tableId):ConfiguredTable{return new ConfiguredTable($this->tableRecord(),[$this->seatRecord()]);}
    public function seat(PrincipalContext $principal,EventScope $scope,int $seatId):SeatingSeat{return $this->seatRecord();}
    public function group(PrincipalContext $principal,EventScope $scope,int $groupId):SeatingGroup{return $this->groupRecord();}
    public function updateTable(PrincipalContext $principal,EventScope $scope,int $tableId,SeatingTableReplacement $replacement,string $idempotencyKey):IdempotencyOutcome{$this->tableReplacement=$replacement;$this->key=$idempotencyKey;$result=new ConfiguredTable(new SeatingTable(5,$replacement->name,$replacement->capacity,$replacement->sortOrder,4),[$this->seatRecord()]);return $this->outcome('seating_table',5,200,$result);}
    public function createSeat(PrincipalContext $principal,EventScope $scope,int $tableId,string $label,bool $accessible,int $sortOrder,string $idempotencyKey):IdempotencyOutcome{$this->seatLabel=$label;$this->key=$idempotencyKey;return $this->outcome('seating_seat',52,201,new SeatingSeat(52,5,$label,$accessible,$sortOrder));}
    public function updateSeat(PrincipalContext $principal,EventScope $scope,int $seatId,SeatingSeatReplacement $replacement,string $idempotencyKey):IdempotencyOutcome{$this->seatReplacement=$replacement;$this->key=$idempotencyKey;return $this->outcome('seating_seat',51,200,new SeatingSeat(51,5,$replacement->label,$replacement->accessible,$replacement->sortOrder,9));}
    public function updateGroup(PrincipalContext $principal,EventScope $scope,int $groupId,SeatingGroupReplacement $replacement,string $idempotencyKey):IdempotencyOutcome{$this->groupReplacement=$replacement;$this->key=$idempotencyKey;return $this->outcome('seating_group',6,200,new SeatingGroup(6,$replacement->name,$replacement->constraintLevel,$replacement->priority,$replacement->attendeeIds,$replacement->category,'host_defined',5));}
    private function tableRecord():SeatingTable{return new SeatingTable(5,'Head Table',5,10,3);}
    private function seatRecord():SeatingSeat{return new SeatingSeat(51,5,'A',true,10,8);}
    private function groupRecord():SeatingGroup{return new SeatingGroup(6,'Family',ConstraintLevel::PREFERRED,10,[7,9],'family','host_defined',4);}
    private function outcome(string $type,int $id,int $status,mixed $response):IdempotencyOutcome{return new IdempotencyOutcome(false,new IdempotencyResultReference($type,$id,$status),$response);}
}
