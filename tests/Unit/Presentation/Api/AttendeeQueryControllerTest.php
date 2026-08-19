<?php

namespace EventFlow\Tests\Unit\Presentation\Api;

use EventFlow\Application\Attendee\{AttendanceStatus, AttendeePage, AttendeeQueries, AttendeeRecord, AttendeeRole};
use EventFlow\Application\Authorization\PrincipalContext;
use EventFlow\Application\Error\RequestIdFactory;
use EventFlow\Application\Persistence\EventScope;
use EventFlow\Application\Security\SecureRandom;
use EventFlow\Presentation\Api\{ApiResponse, AttendeePresenter, AttendeeQueryController, AttendeeQueryRequestMapper, AttendeeQueryRouteRegistrar, AuthenticatedPrincipalResolver, AuthenticatedRequestContextFactory, RequestInputException, RestRequest, RestRouteRegistry};
use PHPUnit\Framework\TestCase;

final class AttendeeQueryControllerTest extends TestCase
{
    public function testRegistrarExposesAuthenticatedCollectionAndDetailGets(): void
    {
        $routes = new AttendeeQueryMemoryRoutes();
        (new AttendeeQueryRouteRegistrar($this->controller(new AttendeeQueryPort())))->register($routes);
        self::assertSame([
            'GET eventflow/v1/events/(?P<event_id>\d+)/attendees',
            'GET eventflow/v1/events/(?P<event_id>\d+)/attendees/(?P<attendee_id>\d+)',
        ], $routes->registered);
    }

    public function testListMapsCursorAndPresentsSensitiveProjectionAsNoStore(): void
    {
        $port = new AttendeeQueryPort();
        $response = $this->controller($port)->list(new RestRequest(
            routeParameters: ['event_id' => '44'],
            queryParameters: ['limit' => '1', 'after' => '100'],
        ));

        self::assertSame(1, $port->limit);
        self::assertSame(100, $port->after);
        self::assertSame(101, $response->body()['meta']['next_after_attendee_id']);
        self::assertSame('guest@example.test', $response->body()['data'][0]['email']);
        self::assertSame('Wheelchair access', $response->body()['data'][0]['accessibility_requirements']);
        self::assertSame('no-store, max-age=0', $response->headers()['Cache-Control']);
    }

    public function testDetailDelegatesEventAndAttendeeIdentifiers(): void
    {
        $port = new AttendeeQueryPort();
        $response = $this->controller($port)->read(new RestRequest(
            routeParameters: ['event_id' => '44', 'attendee_id' => '101'],
        ));

        self::assertSame(44, $port->scope?->eventId);
        self::assertSame(101, $port->attendeeId);
        self::assertSame(81, $response->body()['data']['invitation_id']);
        self::assertArrayNotHasKey('ETag', $response->headers());
    }

    public function testInvalidRouteAndPaginationFailBeforePortInvocation(): void
    {
        $port = new AttendeeQueryPort();
        foreach ([
            fn () => $this->controller($port)->list(new RestRequest(routeParameters: ['event_id' => '44'], queryParameters: ['limit' => '0'])),
            fn () => $this->controller($port)->list(new RestRequest(routeParameters: ['event_id' => '44'], queryParameters: ['after' => '../100'])),
            fn () => $this->controller($port)->read(new RestRequest(routeParameters: ['event_id' => '44', 'attendee_id' => '../101'])),
        ] as $operation) {
            try {
                $operation();
                self::fail('Expected controlled request failure.');
            } catch (RequestInputException $failure) {
                self::assertContains($failure->safeCode, ['validation_failed', 'resource_not_found']);
            }
        }
        self::assertSame(0, $port->calls);
    }

    private function controller(AttendeeQueries $port): AttendeeQueryController
    {
        return new AttendeeQueryController(
            $port,
            new AuthenticatedRequestContextFactory(new AttendeeQueryPrincipalResolver(), new RequestIdFactory(new AttendeeQueryRandom())),
            new AttendeeQueryRequestMapper(),
            new AttendeePresenter(),
        );
    }
}

final class AttendeeQueryPort implements AttendeeQueries
{
    public int $calls = 0;
    public ?int $limit = null;
    public ?int $after = null;
    public ?EventScope $scope = null;
    public ?int $attendeeId = null;

    public function list(PrincipalContext $principal, EventScope $scope, int $limit = 50, ?int $afterAttendeeId = null): AttendeePage
    {
        $this->calls++;
        $this->limit = $limit;
        $this->after = $afterAttendeeId;
        return new AttendeePage([$this->record($scope)], 101);
    }
    public function read(PrincipalContext $principal, EventScope $scope, int $attendeeId): AttendeeRecord
    {
        $this->calls++;
        $this->scope = $scope;
        $this->attendeeId = $attendeeId;
        return $this->record($scope);
    }
    private function record(EventScope $scope): AttendeeRecord
    {
        return new AttendeeRecord(101, $scope, 81, 'Guest', AttendeeRole::PRIMARY, AttendanceStatus::CONFIRMED, 'guest@example.test', '+1 555 0101', 'Vegan', 'Wheelchair access');
    }
}

final readonly class AttendeeQueryPrincipalResolver implements AuthenticatedPrincipalResolver
{
    public function resolve(RestRequest $request): PrincipalContext { return PrincipalContext::wordpressUser(7); }
}
final readonly class AttendeeQueryRandom implements SecureRandom
{
    public function hex(int $bytes): string { return str_repeat('a', $bytes * 2); }
}
final class AttendeeQueryMemoryRoutes implements RestRouteRegistry
{
    /** @var list<string> */ public array $registered = [];
    public function registerPublicGet(string $namespace, string $route, callable $handler): void {}
    public function registerPublicPost(string $namespace, string $route, callable $handler): void {}
    public function registerPublicPut(string $namespace, string $route, callable $handler): void {}
    public function registerAuthenticatedPost(string $namespace, string $route, callable $handler): void {}
    public function registerAuthenticatedGet(string $namespace, string $route, callable $handler): void { $this->registered[] = 'GET ' . $namespace . $route; }
    public function registerAuthenticatedPatch(string $namespace, string $route, callable $handler): void {}
}
