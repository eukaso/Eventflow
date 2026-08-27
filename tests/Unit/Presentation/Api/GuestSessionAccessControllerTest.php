<?php

namespace EventFlow\Tests\Unit\Presentation\Api;

use DateTimeImmutable;
use EventFlow\Application\Attendee\{AttendanceStatus, AttendeeRecord, AttendeeRole, InvitationResponseStatus, RsvpInvitation, RsvpResult};
use EventFlow\Application\Authorization\PrincipalContext;
use EventFlow\Application\Error\RequestIdFactory;
use EventFlow\Application\GuestAccess\{GuestAccessException, GuestInvitationContext, GuestSessionAccess, GuestSessionAuthenticator};
use EventFlow\Application\Invitation\InvitationStatus;
use EventFlow\Application\Persistence\EventScope;
use EventFlow\Application\Security\SecureRandom;
use EventFlow\Presentation\Api\{ApiResponse, GuestRequestContextFactory, GuestSessionAccessController, GuestSessionAccessPresenter, GuestSessionAccessRequestMapper, GuestSessionAccessRouteRegistrar, GuestSessionCookie, RequestInputException, RestRequest, RestRouteRegistry};
use PHPUnit\Framework\TestCase;

final class GuestSessionAccessControllerTest extends TestCase
{
    public function testRegistrarExposesOnlyGuestSessionRoutes(): void
    {
        $routes = new GuestSessionMemoryRoutes();
        (new GuestSessionAccessRouteRegistrar($this->controller(new GuestSessionAccessPort(), new GuestSessionAuthenticatorFake())))->register($routes);
        self::assertSame([
            'GET eventflow/v1/public/invitation',
            'GET eventflow/v1/public/invitation/response',
            'POST eventflow/v1/public/session/logout',
        ], $routes->registered);
    }

    public function testReadsAuthenticateCookieWithoutCsrfAndReturnNoStoreGuestProjections(): void
    {
        $sessions = new GuestSessionAuthenticatorFake();
        $port = new GuestSessionAccessPort();
        $controller = $this->controller($port, $sessions);

        $context = $controller->context($this->readRequest());
        self::assertSame(200, $context->status());
        self::assertSame('Annual Dinner', $context->body()['data']['event_name']);
        self::assertSame('Saturday, November 28, 2026 at 5:00 PM', $context->body()['data']['starts_at_display']);
        self::assertNull($context->body()['data']['ends_at_display']);
        self::assertSame('guest@example.test', $context->body()['data']['primary_email']);
        self::assertSame('+15875550100', $context->body()['data']['primary_phone']);
        self::assertTrue($context->body()['data']['collect_dietary_requirements']);
        self::assertTrue($context->body()['data']['collect_accessibility_requirements']);
        self::assertArrayNotHasKey('organizer_notes', $context->body()['data']);
        self::assertSame('no-store, max-age=0', $context->headers()['Cache-Control']);
        self::assertNull($sessions->csrfToken);
        self::assertFalse($sessions->stateChanging);

        $response = $controller->response($this->readRequest());
        self::assertSame('accepted', $response->body()['data']['response_status']);
        self::assertSame('Guest One', $response->body()['data']['attendees'][0]['display_name']);
        self::assertSame('"7"', $response->headers()['ETag']);
    }

    public function testLogoutRequiresSameOriginCsrfRevokesSessionAndExpiresExactCookiePath(): void
    {
        $sessions = new GuestSessionAuthenticatorFake();
        $port = new GuestSessionAccessPort();
        $response = $this->controller($port, $sessions)->logout(new RestRequest(
            ['X-EventFlow-CSRF' => str_repeat('b', 64)],
            cookies: [GuestSessionCookie::NAME => str_repeat('a', 64)],
            trustedSameOrigin: true,
        ));

        self::assertTrue($port->loggedOut);
        self::assertSame(204, $response->status());
        self::assertSame([], $response->body());
        self::assertStringContainsString('Max-Age=0; Path=/; Secure; HttpOnly; SameSite=Lax', $response->headers()['Set-Cookie']);
        self::assertStringContainsString('Secure; HttpOnly; SameSite=Lax', $response->headers()['Set-Cookie']);
    }

    public function testLogoutRejectsInvalidOriginCsrfAndNonEmptyBodyBeforeRevocation(): void
    {
        foreach ([
            new RestRequest(['X-EventFlow-CSRF' => str_repeat('b', 64)], cookies: [GuestSessionCookie::NAME => str_repeat('a', 64)]),
            new RestRequest(['X-EventFlow-CSRF' => str_repeat('c', 64)], cookies: [GuestSessionCookie::NAME => str_repeat('a', 64)], trustedSameOrigin: true),
            new RestRequest(['X-EventFlow-CSRF' => str_repeat('b', 64)], ['unexpected' => true], cookies: [GuestSessionCookie::NAME => str_repeat('a', 64)], trustedSameOrigin: true),
        ] as $request) {
            $port = new GuestSessionAccessPort();
            try {
                $this->controller($port, new GuestSessionAuthenticatorFake())->logout($request);
                self::fail('Expected logout rejection.');
            } catch (GuestAccessException|RequestInputException $failure) {
                self::assertContains($failure->safeCode, ['guest_csrf_invalid', 'validation_failed']);
            }
            self::assertFalse($port->loggedOut);
        }
    }

    private function readRequest(): RestRequest
    {
        return new RestRequest(cookies: [GuestSessionCookie::NAME => str_repeat('a', 64)]);
    }

    private function controller(GuestSessionAccess $access, GuestSessionAuthenticator $authenticator): GuestSessionAccessController
    {
        return new GuestSessionAccessController(
            $access,
            new GuestRequestContextFactory($authenticator, new RequestIdFactory(new GuestSessionRandom())),
            new GuestSessionAccessRequestMapper(),
            new GuestSessionAccessPresenter(),
        );
    }
}

final class GuestSessionMemoryRoutes implements RestRouteRegistry
{
    public array $registered = [];
    public function registerPublicGet(string $namespace,string $route,callable $handler):void { $this->registered[]='GET '.$namespace.$route; }
    public function registerPublicPost(string $namespace,string $route,callable $handler):void { $this->registered[]='POST '.$namespace.$route; }
    public function registerPublicPut(string $namespace,string $route,callable $handler):void {}
    public function registerAuthenticatedPost(string $namespace,string $route,callable $handler):void {}
    public function registerAuthenticatedGet(string $namespace,string $route,callable $handler):void {}
    public function registerAuthenticatedPatch(string $namespace,string $route,callable $handler):void {}
}

final class GuestSessionAuthenticatorFake implements GuestSessionAuthenticator
{
    public ?string $csrfToken = null;
    public bool $stateChanging = false;
    public function authenticate(string $rawSessionToken, ?string $rawCsrfToken = null, bool $stateChanging = false, bool $sameOrigin = true): PrincipalContext
    {
        $this->csrfToken = $rawCsrfToken;
        $this->stateChanging = $stateChanging;
        if ($rawSessionToken !== str_repeat('a', 64)) throw new GuestAccessException('guest_session_invalid');
        if ($stateChanging && (!$sameOrigin || $rawCsrfToken !== str_repeat('b', 64))) throw new GuestAccessException('guest_csrf_invalid');
        return PrincipalContext::guest(9, new EventScope(44), 81);
    }
}

final class GuestSessionAccessPort implements GuestSessionAccess
{
    public bool $loggedOut = false;
    public function context(PrincipalContext $principal): GuestInvitationContext
    {
        return new GuestInvitationContext(
            new EventScope(44), 81, 'Annual Dinner', 'America/Edmonton',
            new DateTimeImmutable('2026-11-28T17:00:00-07:00'), null, 'Guest One', 2,
            InvitationResponseStatus::ACCEPTED, 7, true, 'Welcome', 'Confirmed', null, 'Formal',
            primaryEmail: 'guest@example.test', primaryPhone: '+15875550100',
        );
    }
    public function response(PrincipalContext $principal): RsvpResult
    {
        return new RsvpResult(
            new RsvpInvitation(81, new EventScope(44), 2, InvitationStatus::ACTIVE, InvitationResponseStatus::ACCEPTED, 7),
            [new AttendeeRecord(101, new EventScope(44), 81, 'Guest One', AttendeeRole::PRIMARY, AttendanceStatus::CONFIRMED, 'guest@example.test')],
        );
    }
    public function logout(PrincipalContext $principal): void { $this->loggedOut = true; }
}

final readonly class GuestSessionRandom implements SecureRandom
{
    public function hex(int $bytes): string { return str_repeat('9', $bytes * 2); }
}
