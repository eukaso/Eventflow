<?php

namespace EventFlow\Tests\Unit\Presentation\Api;

use EventFlow\Application\Authorization\{EventRole, PrincipalContext};
use EventFlow\Application\Error\RequestIdFactory;
use EventFlow\Application\Membership\{MembershipPage, MembershipQueries, MembershipRecord, MembershipStatus};
use EventFlow\Application\Persistence\EventScope;
use EventFlow\Application\Security\SecureRandom;
use EventFlow\Presentation\Api\{ApiResponse, AuthenticatedPrincipalResolver, AuthenticatedRequestContextFactory, MembershipPresenter, MembershipQueryController, MembershipQueryRequestMapper, MembershipQueryRouteRegistrar, RequestInputException, RestRequest, RestRouteRegistry};
use PHPUnit\Framework\TestCase;

final class MembershipQueryControllerTest extends TestCase
{
    public function testRegistrarExposesOnlyAuthenticatedCollectionGet(): void
    {
        $routes = new MembershipQueryMemoryRoutes();
        (new MembershipQueryRouteRegistrar($this->controller(new MembershipQueryPort())))->register($routes);
        self::assertSame(['GET eventflow/v1/events/(?P<event_id>\d+)/memberships'], $routes->registered);
    }

    public function testListMapsScopedCursorAndPresentsMinimizedMemberships(): void
    {
        $port = new MembershipQueryPort();
        $response = $this->controller($port)->list(new RestRequest(
            routeParameters: ['event_id' => '44'],
            queryParameters: ['limit' => '1', 'after' => '70'],
        ));

        self::assertSame(44, $port->scope?->eventId);
        self::assertSame(1, $port->limit);
        self::assertSame(70, $port->after);
        self::assertSame(71, $response->body()['data'][0]['id']);
        self::assertSame('organizer', $response->body()['data'][0]['role']);
        self::assertSame(71, $response->body()['meta']['next_after_membership_id']);
        self::assertSame('no-store, max-age=0', $response->headers()['Cache-Control']);
    }

    public function testInvalidPaginationFailsBeforeQueryInvocation(): void
    {
        $port = new MembershipQueryPort();
        foreach ([['limit' => '0'], ['limit' => '101'], ['after' => '../2']] as $query) {
            try {
                $this->controller($port)->list(new RestRequest(
                    routeParameters: ['event_id' => '44'],
                    queryParameters: $query,
                ));
                self::fail('Expected controlled request failure.');
            } catch (RequestInputException $failure) {
                self::assertSame('validation_failed', $failure->safeCode);
            }
        }
        self::assertSame(0, $port->calls);
    }

    private function controller(MembershipQueries $port): MembershipQueryController
    {
        return new MembershipQueryController(
            $port,
            new AuthenticatedRequestContextFactory(
                new MembershipQueryPrincipalResolver(),
                new RequestIdFactory(new MembershipQueryRandom()),
            ),
            new MembershipQueryRequestMapper(),
            new MembershipPresenter(),
        );
    }
}

final class MembershipQueryPort implements MembershipQueries
{
    public int $calls = 0;
    public ?EventScope $scope = null;
    public ?int $limit = null;
    public ?int $after = null;

    public function list(PrincipalContext $principal, EventScope $scope, int $limit = 50, ?int $afterMembershipId = null): MembershipPage
    {
        $this->calls++;
        $this->scope = $scope;
        $this->limit = $limit;
        $this->after = $afterMembershipId;
        return new MembershipPage([
            new MembershipRecord(71, $scope, 23, EventRole::ORGANIZER, MembershipStatus::ACTIVE, false, null),
        ], 71);
    }
}

final readonly class MembershipQueryPrincipalResolver implements AuthenticatedPrincipalResolver
{
    public function resolve(RestRequest $request): PrincipalContext
    {
        return PrincipalContext::wordpressUser(7);
    }
}

final readonly class MembershipQueryRandom implements SecureRandom
{
    public function hex(int $bytes): string
    {
        return str_repeat('e', $bytes * 2);
    }
}

final class MembershipQueryMemoryRoutes implements RestRouteRegistry
{
    /** @var list<string> */
    public array $registered = [];
    public function registerPublicGet(string $namespace, string $route, callable $handler): void {}
    public function registerPublicPost(string $namespace, string $route, callable $handler): void {}
    public function registerPublicPut(string $namespace, string $route, callable $handler): void {}
    public function registerAuthenticatedPost(string $namespace, string $route, callable $handler): void {}
    public function registerAuthenticatedGet(string $namespace, string $route, callable $handler): void { $this->registered[] = 'GET ' . $namespace . $route; }
    public function registerAuthenticatedPatch(string $namespace, string $route, callable $handler): void {}
}
