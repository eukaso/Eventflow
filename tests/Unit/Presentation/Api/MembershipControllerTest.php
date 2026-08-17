<?php

namespace EventFlow\Tests\Unit\Presentation\Api;

use DateTimeImmutable;
use EventFlow\Application\Authorization\{EventRole, PrincipalContext};
use EventFlow\Application\Error\RequestIdFactory;
use EventFlow\Application\Idempotency\{IdempotencyOutcome, IdempotencyResultReference};
use EventFlow\Application\Membership\{ChangeMembership, GrantMembership, MembershipCommands, MembershipRecord, MembershipStatus, TransferPrimaryOwner};
use EventFlow\Application\Persistence\EventScope;
use EventFlow\Application\Security\SecureRandom;
use EventFlow\Presentation\Api\{ApiResponse, AuthenticatedPrincipalResolver, AuthenticatedRequestContextFactory, MembershipCommand, MembershipController, MembershipPresenter, MembershipRequestMapper, MembershipRouteRegistrar, RequestInputException, RestRequest, RestRouteRegistry};
use PHPUnit\Framework\TestCase;

final class MembershipControllerTest extends TestCase
{
    public function testRegistrarExposesAllAuthoritativeMutationRoutes(): void
    {
        $routes = new MembershipMemoryRoutes();
        (new MembershipRouteRegistrar($this->controller(new MembershipCommandPort())))->register($routes);

        self::assertSame([
            'POST eventflow/v1/events/(?P<event_id>\d+)/memberships',
            'PATCH eventflow/v1/events/(?P<event_id>\d+)/memberships/(?P<membership_id>\d+)',
            'POST eventflow/v1/events/(?P<event_id>\d+)/memberships/(?P<membership_id>\d+)/suspend',
            'POST eventflow/v1/events/(?P<event_id>\d+)/memberships/(?P<membership_id>\d+)/reactivate',
            'POST eventflow/v1/events/(?P<event_id>\d+)/memberships/(?P<membership_id>\d+)/revoke',
            'POST eventflow/v1/events/(?P<event_id>\d+)/memberships/(?P<membership_id>\d+)/make-primary-owner',
        ], $routes->registered);
    }

    public function testGrantMapsStrictInputAndPresentsMembership(): void
    {
        $port = new MembershipCommandPort();
        $response = $this->controller($port)->grant(new RestRequest(
            ['Idempotency-Key' => 'membership-grant-001'],
            ['user_id' => 23, 'role' => 'organizer', 'expires_at' => '2027-01-02T03:04:05+00:00'],
            ['event_id' => '44'],
        ));

        self::assertSame('grant', $port->calls[0]);
        self::assertSame('membership-grant-001', $port->keys[0]);
        self::assertSame(201, $response->status());
        self::assertSame(44, $response->body()['data']['event_id']);
        self::assertSame('organizer', $response->body()['data']['role']);
        self::assertSame('2027-01-02T03:04:05Z', $response->body()['data']['expires_at']);
        self::assertSame('/wp-json/eventflow/v1/events/44/memberships/71', $response->headers()['Location']);
    }

    public function testChangeLifecycleAndTransferDelegateExplicitCommands(): void
    {
        $port = new MembershipCommandPort();
        $controller = $this->controller($port);
        $route = ['event_id' => '44', 'membership_id' => '71'];

        $controller->change(new RestRequest(
            ['Idempotency-Key' => 'membership-change-001'],
            ['role' => 'coordinator', 'expires_at' => null],
            $route,
        ));
        foreach (MembershipCommand::cases() as $command) {
            $controller->transition(new RestRequest(
                ['Idempotency-Key' => 'membership-' . $command->value],
                routeParameters: $route,
            ), $command);
        }
        $controller->makePrimaryOwner(new RestRequest(
            ['Idempotency-Key' => 'membership-primary-001'],
            ['expected_current_membership_id' => 7],
            $route,
        ));

        self::assertSame(['change', 'suspend', 'reactivate', 'revoke', 'transfer'], $port->calls);
        self::assertSame(7, $port->transfer?->expectedCurrentMembershipId);
        self::assertSame(71, $port->transfer?->targetMembershipId);
    }

    public function testReplayUsesStableReferenceAndScopedLocation(): void
    {
        $response = $this->controller(new MembershipCommandPort(true))->transition(new RestRequest(
            ['Idempotency-Key' => 'membership-replay-001'],
            routeParameters: ['event_id' => '44', 'membership_id' => '71'],
        ), MembershipCommand::SUSPEND);

        self::assertSame(['type' => 'membership', 'id' => 71], $response->body()['data']);
        self::assertTrue($response->body()['meta']['replayed']);
        self::assertSame('/wp-json/eventflow/v1/events/44/memberships/71', $response->headers()['Location']);
    }

    public function testInvalidInputsFailBeforeServiceInvocation(): void
    {
        $port = new MembershipCommandPort();
        foreach ([
            fn () => $this->controller($port)->grant(new RestRequest(['Idempotency-Key'=>'membership-invalid-001'], ['user_id'=>4,'role'=>'admin'], ['event_id'=>'44'])),
            fn () => $this->controller($port)->grant(new RestRequest(['Idempotency-Key'=>'membership-invalid-002'], ['user_id'=>4,'role'=>'organizer','extra'=>true], ['event_id'=>'44'])),
            fn () => $this->controller($port)->change(new RestRequest(['Idempotency-Key'=>'membership-invalid-003'], ['role'=>'owner','expires_at'=>'2026-02-31T10:00:00Z'], ['event_id'=>'44','membership_id'=>'71'])),
            fn () => $this->controller($port)->transition(new RestRequest(['Idempotency-Key'=>'membership-invalid-004'], ['unexpected'=>true], ['event_id'=>'44','membership_id'=>'71']), MembershipCommand::SUSPEND),
            fn () => $this->controller($port)->makePrimaryOwner(new RestRequest(['Idempotency-Key'=>'membership-invalid-005'], ['expected_current_membership_id'=>7], ['event_id'=>'44','membership_id'=>'../71'])),
        ] as $operation) {
            try { $operation(); self::fail('Expected controlled request failure.'); }
            catch (RequestInputException $failure) { self::assertContains($failure->safeCode, ['validation_failed', 'resource_not_found']); }
        }
        self::assertSame([], $port->calls);
    }

    private function controller(MembershipCommands $port): MembershipController
    {
        return new MembershipController(
            $port,
            new AuthenticatedRequestContextFactory(new MembershipPrincipalResolver(), new RequestIdFactory(new MembershipRandom())),
            new MembershipRequestMapper(),
            new MembershipPresenter(),
        );
    }
}

final class MembershipMemoryRoutes implements RestRouteRegistry
{
    public array $registered = [];
    public function registerPublicGet(string $namespace, string $route, callable $handler): void {}
    public function registerPublicPost(string $namespace, string $route, callable $handler): void {}
    public function registerAuthenticatedPost(string $namespace, string $route, callable $handler): void { $this->registered[] = 'POST ' . $namespace . $route; }
    public function registerAuthenticatedPatch(string $namespace, string $route, callable $handler): void { $this->registered[] = 'PATCH ' . $namespace . $route; }
}

final readonly class MembershipPrincipalResolver implements AuthenticatedPrincipalResolver
{
    public function resolve(RestRequest $request): PrincipalContext { return PrincipalContext::wordpressUser(7); }
}

final readonly class MembershipRandom implements SecureRandom
{
    public function hex(int $bytes): string { return str_repeat('d', $bytes * 2); }
}

final class MembershipCommandPort implements MembershipCommands
{
    public array $calls = [];
    public array $keys = [];
    public ?TransferPrimaryOwner $transfer = null;

    public function __construct(private bool $replay = false) {}

    public function grant(PrincipalContext $principal, GrantMembership $command, string $idempotencyKey): IdempotencyOutcome
    {
        return $this->result('grant', $command->eventScope, 71, $idempotencyKey, 201, $command->userId, $command->role, $command->expiresAt);
    }

    public function change(PrincipalContext $principal, ChangeMembership $command, string $idempotencyKey): IdempotencyOutcome
    {
        return $this->result('change', $command->eventScope, $command->membershipId, $idempotencyKey, 200, 23, $command->role, $command->expiresAt);
    }

    public function suspend(PrincipalContext $principal, EventScope $scope, int $membershipId, string $idempotencyKey): IdempotencyOutcome { return $this->result('suspend', $scope, $membershipId, $idempotencyKey, 200); }
    public function reactivate(PrincipalContext $principal, EventScope $scope, int $membershipId, string $idempotencyKey): IdempotencyOutcome { return $this->result('reactivate', $scope, $membershipId, $idempotencyKey, 200); }
    public function revoke(PrincipalContext $principal, EventScope $scope, int $membershipId, string $idempotencyKey): IdempotencyOutcome { return $this->result('revoke', $scope, $membershipId, $idempotencyKey, 200); }

    public function transferPrimaryOwner(PrincipalContext $principal, TransferPrimaryOwner $command, string $idempotencyKey): IdempotencyOutcome
    {
        $this->transfer = $command;
        return $this->result('transfer', $command->eventScope, $command->targetMembershipId, $idempotencyKey, 200, role: EventRole::OWNER, primary: true);
    }

    private function result(string $call, EventScope $scope, int $id, string $key, int $status, int $userId = 23, EventRole $role = EventRole::ORGANIZER, ?DateTimeImmutable $expiry = null, bool $primary = false): IdempotencyOutcome
    {
        $this->calls[] = $call;
        $this->keys[] = $key;
        $reference = new IdempotencyResultReference('membership', $id, $status);
        if ($this->replay) return new IdempotencyOutcome(true, $reference);
        return new IdempotencyOutcome(false, $reference, new MembershipRecord($id, $scope, $userId, $role, MembershipStatus::ACTIVE, $primary, $expiry));
    }
}
