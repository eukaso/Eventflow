<?php

namespace EventFlow\Tests\Unit\Presentation\Api;

use EventFlow\Application\Attendee\{AttendanceStatus, AttendeeRecord, AttendeeRole, InvitationResponseStatus, RsvpCommands, RsvpInvitation, RsvpResult, SubmitRsvp};
use EventFlow\Application\Authorization\PrincipalContext;
use EventFlow\Application\Error\RequestIdFactory;
use EventFlow\Application\GuestAccess\{GuestAccessException, GuestSessionAuthenticator};
use EventFlow\Application\Idempotency\{IdempotencyOutcome, IdempotencyResultReference};
use EventFlow\Application\Invitation\InvitationStatus;
use EventFlow\Application\Persistence\EventScope;
use EventFlow\Application\Security\SecureRandom;
use EventFlow\Presentation\Api\{ApiResponse, GuestRequestContextFactory, GuestSessionCookie, RequestInputException, RestRequest, RestRouteRegistry, RsvpController, RsvpPresenter, RsvpRequestMapper, RsvpRouteRegistrar};
use PHPUnit\Framework\TestCase;

final class RsvpControllerTest extends TestCase
{
    public function testRegistrarExposesOnlyGuestRsvpPut(): void
    {
        $routes = new RsvpMemoryRoutes();
        (new RsvpRouteRegistrar($this->controller(new RsvpCommandPort(), new RsvpSessionAuthenticator())))->register($routes);
        self::assertSame(['PUT eventflow/v1/public/invitation/response'], $routes->registered);
    }

    public function testSubmitUsesCookieScopeCsrfIdempotencyAndIfMatch(): void
    {
        $port = new RsvpCommandPort();
        $sessions = new RsvpSessionAuthenticator();
        $response = $this->controller($port, $sessions)->submit($this->request([
            'response_status' => 'accepted',
            'attendees' => [[
                'display_name' => 'Guest One', 'role' => 'primary',
                'email' => 'guest@example.test', 'dietary_requirements' => 'Vegetarian',
            ]],
        ]));

        self::assertSame(str_repeat('a', 64), $sessions->sessionToken);
        self::assertSame(str_repeat('b', 64), $sessions->csrfToken);
        self::assertTrue($sessions->stateChanging);
        self::assertTrue($sessions->sameOrigin);
        self::assertSame(44, $port->command?->eventScope->eventId);
        self::assertSame(81, $port->command?->invitationId);
        self::assertSame(3, $port->command?->expectedRevision);
        self::assertSame('rsvp-submit-001', $port->key);
        self::assertSame(200, $response->status());
        self::assertSame('accepted', $response->body()['data']['response_status']);
        self::assertSame('Guest One', $response->body()['data']['attendees'][0]['display_name']);
        self::assertSame('"4"', $response->headers()['ETag']);
    }

    public function testDeclineAcceptsCompleteEmptyAttendeeState(): void
    {
        $port = new RsvpCommandPort();
        $this->controller($port, new RsvpSessionAuthenticator())->submit($this->request([
            'response_status' => 'declined', 'attendees' => [],
        ]));
        self::assertSame(InvitationResponseStatus::DECLINED, $port->command?->responseStatus);
        self::assertSame([], $port->command?->attendees);
    }

    public function testOriginCsrfSessionAndPreconditionsFailBeforeRsvpCommand(): void
    {
        $body = ['response_status' => 'declined', 'attendees' => []];
        $cases = [
            $this->request($body, sameOrigin: false),
            $this->request($body, csrf: str_repeat('c', 64)),
            new RestRequest(['X-EventFlow-CSRF'=>str_repeat('b',64),'Idempotency-Key'=>'rsvp-submit-001','If-Match'=>'"3"'], $body, cookies: [], trustedSameOrigin: true),
            new RestRequest(['X-EventFlow-CSRF'=>str_repeat('b',64),'If-Match'=>'"3"'], $body, cookies:[GuestSessionCookie::NAME=>str_repeat('a',64)], trustedSameOrigin:true),
        ];
        foreach ($cases as $request) {
            $port = new RsvpCommandPort();
            try { $this->controller($port, new RsvpSessionAuthenticator())->submit($request); self::fail('Expected security failure.'); }
            catch (GuestAccessException|RequestInputException $failure) {
                $code = $failure instanceof GuestAccessException ? $failure->safeCode : $failure->safeCode;
                self::assertContains($code, ['guest_csrf_invalid', 'guest_session_invalid', 'precondition_required']);
            }
            self::assertNull($port->command);
        }
    }

    public function testCallerScopeFieldsAndMalformedAttendeesAreRejected(): void
    {
        foreach ([
            ['response_status'=>'accepted','attendees'=>[],'event_id'=>999],
            ['response_status'=>'pending','attendees'=>[]],
            ['response_status'=>'accepted','attendees'=>[['display_name'=>'Guest','role'=>'owner']]],
            ['response_status'=>'accepted','attendees'=>[['display_name'=>'Guest','role'=>'primary','admin'=>true]]],
        ] as $body) {
            $port = new RsvpCommandPort();
            try { $this->controller($port, new RsvpSessionAuthenticator())->submit($this->request($body)); self::fail('Expected validation failure.'); }
            catch (RequestInputException $failure) { self::assertSame('validation_failed', $failure->safeCode); }
            self::assertNull($port->command);
        }
    }

    /** @param array<string, mixed> $body */
    private function request(array $body, bool $sameOrigin = true, string $csrf = ''): RestRequest
    {
        return new RestRequest(
            ['X-EventFlow-CSRF'=>$csrf === '' ? str_repeat('b',64) : $csrf,'Idempotency-Key'=>'rsvp-submit-001','If-Match'=>'"3"'],
            $body,
            cookies: [GuestSessionCookie::NAME => str_repeat('a', 64)],
            trustedSameOrigin: $sameOrigin,
        );
    }

    private function controller(RsvpCommands $commands, GuestSessionAuthenticator $sessions): RsvpController
    {
        return new RsvpController(
            $commands,
            new GuestRequestContextFactory($sessions, new RequestIdFactory(new RsvpRandom())),
            new RsvpRequestMapper(),
            new RsvpPresenter(),
        );
    }
}

final class RsvpMemoryRoutes implements RestRouteRegistry
{
    public array $registered = [];
    public function registerPublicGet(string $namespace,string $route,callable $handler):void {}
    public function registerPublicPost(string $namespace,string $route,callable $handler):void {}
    public function registerPublicPut(string $namespace,string $route,callable $handler):void { $this->registered[]='PUT '.$namespace.$route; }
    public function registerAuthenticatedPost(string $namespace,string $route,callable $handler):void {}
    public function registerAuthenticatedPatch(string $namespace,string $route,callable $handler):void {}
}

final class RsvpSessionAuthenticator implements GuestSessionAuthenticator
{
    public ?string $sessionToken = null;
    public ?string $csrfToken = null;
    public bool $stateChanging = false;
    public bool $sameOrigin = false;
    public function authenticate(string $rawSessionToken, ?string $rawCsrfToken = null, bool $stateChanging = false, bool $sameOrigin = true): PrincipalContext
    {
        $this->sessionToken=$rawSessionToken; $this->csrfToken=$rawCsrfToken; $this->stateChanging=$stateChanging; $this->sameOrigin=$sameOrigin;
        if (!$sameOrigin || $rawCsrfToken !== str_repeat('b',64)) throw new GuestAccessException('guest_csrf_invalid');
        return PrincipalContext::guest(9, new EventScope(44), 81);
    }
}

final class RsvpCommandPort implements RsvpCommands
{
    public ?SubmitRsvp $command = null;
    public ?string $key = null;
    public function submitRsvp(PrincipalContext $principal, SubmitRsvp $command, string $idempotencyKey): IdempotencyOutcome
    {
        $this->command=$command; $this->key=$idempotencyKey;
        $invitation=new RsvpInvitation(81,new EventScope(44),4,InvitationStatus::ACTIVE,$command->responseStatus,$command->expectedRevision+1);
        $attendees=[];
        foreach ($command->attendees as $index=>$desired) {
            $attendees[]=new AttendeeRecord($desired->attendeeId??($index+101),new EventScope(44),81,$desired->displayName,$desired->role,AttendanceStatus::CONFIRMED,$desired->email,$desired->phone,$desired->dietaryRequirements,$desired->accessibilityRequirements);
        }
        return new IdempotencyOutcome(false,new IdempotencyResultReference('invitation',81,200),new RsvpResult($invitation,$attendees));
    }
}

final readonly class RsvpRandom implements SecureRandom
{
    public function hex(int $bytes): string { return str_repeat('9',$bytes*2); }
}
