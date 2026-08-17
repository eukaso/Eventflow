<?php

namespace EventFlow\Tests\Unit\Presentation\Api;

use EventFlow\Application\Attendee\{AttendanceStatus, AttendeeCommands, AttendeeRecord, AttendeeRole, DesiredAttendee};
use EventFlow\Application\Authorization\PrincipalContext;
use EventFlow\Application\Error\RequestIdFactory;
use EventFlow\Application\Idempotency\{IdempotencyOutcome, IdempotencyResultReference};
use EventFlow\Application\Persistence\EventScope;
use EventFlow\Application\Security\SecureRandom;
use EventFlow\Presentation\Api\{ApiResponse, AttendeeCommand, AttendeeController, AttendeePresenter, AttendeeRequestMapper, AttendeeRouteRegistrar, AuthenticatedPrincipalResolver, AuthenticatedRequestContextFactory, RequestInputException, RestRequest, RestRouteRegistry};
use PHPUnit\Framework\TestCase;

final class AttendeeControllerTest extends TestCase
{
    public function testRegistrarExposesAllAuthoritativeAttendeeMutations(): void
    {
        $routes = new AttendeeMemoryRoutes();
        (new AttendeeRouteRegistrar($this->controller(new AttendeeCommandPort())))->register($routes);
        self::assertSame([
            'POST eventflow/v1/events/(?P<event_id>\d+)/attendees',
            'PATCH eventflow/v1/events/(?P<event_id>\d+)/attendees/(?P<attendee_id>\d+)',
            'POST eventflow/v1/events/(?P<event_id>\d+)/attendees/(?P<attendee_id>\d+)/cancel',
            'POST eventflow/v1/events/(?P<event_id>\d+)/attendees/(?P<attendee_id>\d+)/restore',
            'POST eventflow/v1/events/(?P<event_id>\d+)/attendees/(?P<attendee_id>\d+)/make-primary',
        ], $routes->registered);
    }

    public function testCreateMapsInvitationAndAttendeeDetails(): void
    {
        $port = new AttendeeCommandPort();
        $response = $this->controller($port)->create(new RestRequest(
            ['Idempotency-Key'=>'attendee-create-001'],
            ['invitation_id'=>81,'display_name'=>'Guest Two','role'=>'companion','email'=>'two@example.test','dietary_requirements'=>'Vegan'],
            ['event_id'=>'44'],
        ));
        self::assertSame('create', $port->calls[0]);
        self::assertSame(81, $port->invitationIds[0]);
        self::assertSame('attendee-create-001', $port->keys[0]);
        self::assertSame(201, $response->status());
        self::assertSame('Guest Two', $response->body()['data']['display_name']);
        self::assertSame('/wp-json/eventflow/v1/events/44/attendees/102', $response->headers()['Location']);
    }

    public function testUpdateLifecycleAndPrimaryTransferDelegateExplicitCommands(): void
    {
        $port = new AttendeeCommandPort();
        $controller = $this->controller($port);
        $route = ['event_id'=>'44','attendee_id'=>'102'];
        $controller->update(new RestRequest(
            ['Idempotency-Key'=>'attendee-update-001'],
            ['invitation_id'=>81,'display_name'=>'Corrected Guest','role'=>'companion','phone'=>'+1 555 0102'],
            $route,
        ));
        foreach (AttendeeCommand::cases() as $command) {
            $controller->transition(new RestRequest(
                ['Idempotency-Key'=>'attendee-'.$command->value],
                ['invitation_id'=>81],
                $route,
            ), $command);
        }
        $controller->makePrimary(new RestRequest(
            ['Idempotency-Key'=>'attendee-primary-001'],
            ['invitation_id'=>81,'expected_primary_attendee_id'=>101],
            $route,
        ));
        self::assertSame(['update','cancel','restore','transfer'], $port->calls);
        self::assertSame(101, $port->expectedPrimaryId);
        self::assertSame(102, $port->targetId);
    }

    public function testInvalidBodiesAndRouteIdsFailBeforeService(): void
    {
        $port = new AttendeeCommandPort();
        foreach ([
            fn () => $this->controller($port)->create(new RestRequest(['Idempotency-Key'=>'attendee-invalid-001'], ['invitation_id'=>81,'display_name'=>'Guest','role'=>'owner'], ['event_id'=>'44'])),
            fn () => $this->controller($port)->create(new RestRequest(['Idempotency-Key'=>'attendee-invalid-002'], ['invitation_id'=>81,'display_name'=>'Guest','role'=>'companion','admin'=>true], ['event_id'=>'44'])),
            fn () => $this->controller($port)->update(new RestRequest(['Idempotency-Key'=>'attendee-invalid-003'], ['invitation_id'=>81,'display_name'=>'Guest','role'=>'companion'], ['event_id'=>'44','attendee_id'=>'../2'])),
            fn () => $this->controller($port)->transition(new RestRequest(['Idempotency-Key'=>'attendee-invalid-004'], ['invitation_id'=>'81'], ['event_id'=>'44','attendee_id'=>'2']), AttendeeCommand::CANCEL),
            fn () => $this->controller($port)->makePrimary(new RestRequest(['Idempotency-Key'=>'attendee-invalid-005'], ['invitation_id'=>81], ['event_id'=>'44','attendee_id'=>'2'])),
        ] as $operation) {
            try { $operation(); self::fail('Expected controlled input failure.'); }
            catch (RequestInputException $failure) { self::assertContains($failure->safeCode, ['validation_failed','resource_not_found']); }
        }
        self::assertSame([], $port->calls);
    }

    private function controller(AttendeeCommands $commands): AttendeeController
    {
        return new AttendeeController(
            $commands,
            new AuthenticatedRequestContextFactory(new AttendeePrincipalResolver(), new RequestIdFactory(new AttendeeRandom())),
            new AttendeeRequestMapper(),
            new AttendeePresenter(),
        );
    }
}

final class AttendeeMemoryRoutes implements RestRouteRegistry
{
    public array $registered=[];
    public function registerPublicGet(string $namespace,string $route,callable $handler):void {}
    public function registerPublicPost(string $namespace,string $route,callable $handler):void {}
    public function registerPublicPut(string $namespace,string $route,callable $handler):void {}
    public function registerAuthenticatedPost(string $namespace,string $route,callable $handler):void {$this->registered[]='POST '.$namespace.$route;}
    public function registerAuthenticatedPatch(string $namespace,string $route,callable $handler):void {$this->registered[]='PATCH '.$namespace.$route;}
}

final readonly class AttendeePrincipalResolver implements AuthenticatedPrincipalResolver
{
    public function resolve(RestRequest $request): PrincipalContext { return PrincipalContext::wordpressUser(7); }
}

final readonly class AttendeeRandom implements SecureRandom
{
    public function hex(int $bytes): string { return str_repeat('8',$bytes*2); }
}

final class AttendeeCommandPort implements AttendeeCommands
{
    public array $calls=[];
    public array $invitationIds=[];
    public array $keys=[];
    public ?int $expectedPrimaryId=null;
    public ?int $targetId=null;

    public function createAttendee(PrincipalContext $principal,EventScope $scope,int $invitationId,DesiredAttendee $desired,string $idempotencyKey):IdempotencyOutcome
    { return $this->result('create',$scope,$invitationId,102,$desired,$idempotencyKey,201); }

    public function updateAttendee(PrincipalContext $principal,EventScope $scope,int $invitationId,int $attendeeId,DesiredAttendee $desired,string $idempotencyKey):IdempotencyOutcome
    { return $this->result('update',$scope,$invitationId,$attendeeId,$desired,$idempotencyKey,200); }

    public function cancel(PrincipalContext $principal,EventScope $scope,int $invitationId,int $attendeeId,string $idempotencyKey):IdempotencyOutcome
    { return $this->status('cancel',$scope,$invitationId,$attendeeId,$idempotencyKey,AttendanceStatus::CANCELLED); }

    public function restore(PrincipalContext $principal,EventScope $scope,int $invitationId,int $attendeeId,string $idempotencyKey):IdempotencyOutcome
    { return $this->status('restore',$scope,$invitationId,$attendeeId,$idempotencyKey,AttendanceStatus::CONFIRMED); }

    public function transferPrimary(PrincipalContext $principal,EventScope $scope,int $invitationId,int $expectedPrimaryId,int $targetId,string $idempotencyKey):IdempotencyOutcome
    {
        $this->expectedPrimaryId=$expectedPrimaryId; $this->targetId=$targetId;
        return $this->result('transfer',$scope,$invitationId,$targetId,new DesiredAttendee('Corrected Guest',AttendeeRole::PRIMARY,$targetId),$idempotencyKey,200);
    }

    private function status(string $call,EventScope $scope,int $invitationId,int $id,string $key,AttendanceStatus $status):IdempotencyOutcome
    {
        $this->calls[]=$call; $this->invitationIds[]=$invitationId; $this->keys[]=$key;
        $record=new AttendeeRecord($id,$scope,$invitationId,'Guest Two',AttendeeRole::COMPANION,$status);
        return new IdempotencyOutcome(false,new IdempotencyResultReference('attendee',$id,200),$record);
    }

    private function result(string $call,EventScope $scope,int $invitationId,int $id,DesiredAttendee $desired,string $key,int $status):IdempotencyOutcome
    {
        $this->calls[]=$call; $this->invitationIds[]=$invitationId; $this->keys[]=$key;
        $record=new AttendeeRecord($id,$scope,$invitationId,$desired->displayName,$desired->role,AttendanceStatus::CONFIRMED,$desired->email,$desired->phone,$desired->dietaryRequirements,$desired->accessibilityRequirements);
        return new IdempotencyOutcome(false,new IdempotencyResultReference('attendee',$id,$status),$record);
    }
}
