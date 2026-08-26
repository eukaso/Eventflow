<?php

namespace EventFlow\Tests\Unit\Presentation\Api;

use DateTimeImmutable;
use EventFlow\Application\Authorization\PrincipalContext;
use EventFlow\Application\Error\RequestIdFactory;
use EventFlow\Application\Idempotency\{IdempotencyOutcome, IdempotencyResultReference};
use EventFlow\Application\Invitation\{CompanionRolloutResult, InvitationOperations, InvitationPage, InvitationPatch, InvitationRecord, InvitationStatus};
use EventFlow\Application\Persistence\EventScope;
use EventFlow\Application\Security\SecureRandom;
use EventFlow\Presentation\Api\{ApiResponse, AuthenticatedPrincipalResolver, AuthenticatedRequestContextFactory, InvitationAccessCommand, InvitationAccessController, InvitationAccessRequestMapper, InvitationAccessRouteRegistrar, InvitationPresenter, RequestInputException, RestRequest, RestRouteRegistry};
use PHPUnit\Framework\TestCase;

final class InvitationAccessControllerTest extends TestCase
{
    public function testRegistrarExposesAcceptedAccessAndLifecycleRoutes(): void
    {
        $routes = new InvitationAccessMemoryRoutes();
        (new InvitationAccessRouteRegistrar($this->controller(new InvitationAccessPort())))->register($routes);

        self::assertSame([
            'GET eventflow/v1/events/(?P<event_id>\d+)/invitations',
            'GET eventflow/v1/events/(?P<event_id>\d+)/invitations/(?P<invitation_id>\d+)',
            'PATCH eventflow/v1/events/(?P<event_id>\d+)/invitations/(?P<invitation_id>\d+)',
            'POST eventflow/v1/events/(?P<event_id>\d+)/invitations/apply-companion-rollout',
            'POST eventflow/v1/events/(?P<event_id>\d+)/invitations/(?P<invitation_id>\d+)/archive',
            'POST eventflow/v1/events/(?P<event_id>\d+)/invitations/(?P<invitation_id>\d+)/restore',
        ], $routes->registered);
    }

    public function testListAndDetailPresentCursorRevisionAndNoStore(): void
    {
        $port = new InvitationAccessPort();
        $controller = $this->controller($port);
        $list = $controller->list(new RestRequest(
            routeParameters: ['event_id' => '44'],
            queryParameters: ['limit' => '1', 'after' => '80'],
        ));
        $detail = $controller->read(new RestRequest(routeParameters: ['event_id' => '44', 'invitation_id' => '81']));

        self::assertSame(1, $port->limit);
        self::assertSame(80, $port->after);
        self::assertSame(81, $list->body()['meta']['next_after_invitation_id']);
        self::assertSame('guest@example.test', $detail->body()['data']['primary_email']);
        self::assertSame(4, $detail->body()['data']['revision']);
        self::assertSame('"4"', $detail->headers()['ETag']);
        self::assertSame('no-store, max-age=0', $detail->headers()['Cache-Control']);
    }

    public function testPatchRequiresDualPreconditionsAndMapsStrictFields(): void
    {
        $port = new InvitationAccessPort();
        $response = $this->controller($port)->update(new RestRequest(
            ['If-Match' => '"4"', 'Idempotency-Key' => 'invitation-update-001'],
            ['primary_name' => 'Updated Guest', 'primary_email' => null, 'capacity' => 3],
            ['event_id' => '44', 'invitation_id' => '81'],
        ));

        self::assertSame('update', $port->calls[0]);
        self::assertSame(4, $port->patch?->expectedRevision);
        self::assertSame('Updated Guest', $port->patch?->changes['primary_name']);
        self::assertNull($port->patch?->changes['primary_email']);
        self::assertSame('invitation-update-001', $port->keys[0]);
        self::assertSame('"5"', $response->headers()['ETag']);
    }

    public function testArchiveAndRestoreDelegateExplicitEmptyBodyCommands(): void
    {
        $port = new InvitationAccessPort();
        $controller = $this->controller($port);
        $route = ['event_id' => '44', 'invitation_id' => '81'];
        foreach (InvitationAccessCommand::cases() as $command) {
            $controller->transition(new RestRequest(
                ['Idempotency-Key' => 'invitation-' . $command->value],
                routeParameters: $route,
            ), $command);
        }
        self::assertSame(['archive', 'restore'], $port->calls);
        self::assertSame(['invitation-archive', 'invitation-restore'], $port->keys);
    }

    public function testCompanionRolloutReturnsUpdatedCount(): void
    {
        $port = new InvitationAccessPort();
        $response = $this->controller($port)->applyCompanionRollout(new RestRequest(
            ['Idempotency-Key' => 'companion-rollout-001'],
            routeParameters: ['event_id' => '44'],
        ));

        self::assertSame('rollout', $port->calls[0]);
        self::assertSame(3, $response->body()['data']['updated_invitations']);
        self::assertSame(2, $response->body()['data']['total_capacity']);
    }

    public function testInvalidInputsFailBeforePortInvocation(): void
    {
        $port = new InvitationAccessPort();
        foreach ([
            fn () => $this->controller($port)->list(new RestRequest(routeParameters: ['event_id' => '44'], queryParameters: ['limit' => '0'])),
            fn () => $this->controller($port)->read(new RestRequest(routeParameters: ['event_id' => '44', 'invitation_id' => '../81'])),
            fn () => $this->controller($port)->update(new RestRequest(['If-Match'=>'"4"','Idempotency-Key'=>'invitation-bad-001'], ['unknown'=>true], ['event_id'=>'44','invitation_id'=>'81'])),
            fn () => $this->controller($port)->transition(new RestRequest(['Idempotency-Key'=>'invitation-bad-002'], ['unexpected'=>true], ['event_id'=>'44','invitation_id'=>'81']), InvitationAccessCommand::ARCHIVE),
        ] as $operation) {
            try {
                $operation();
                self::fail('Expected controlled request failure.');
            } catch (RequestInputException $failure) {
                self::assertContains($failure->safeCode, ['validation_failed', 'resource_not_found']);
            }
        }
        self::assertSame([], $port->calls);
    }

    private function controller(InvitationOperations $port): InvitationAccessController
    {
        return new InvitationAccessController(
            $port,
            new AuthenticatedRequestContextFactory(new InvitationAccessPrincipalResolver(), new RequestIdFactory(new InvitationAccessRandom())),
            new InvitationAccessRequestMapper(),
            new InvitationPresenter(),
        );
    }
}

final class InvitationAccessPort implements InvitationOperations
{
    /** @var list<string> */ public array $calls = [];
    /** @var list<string> */ public array $keys = [];
    public ?int $limit = null;
    public ?int $after = null;
    public ?InvitationPatch $patch = null;

    public function list(PrincipalContext $principal, EventScope $scope, int $limit = 50, ?int $afterInvitationId = null): InvitationPage
    {
        $this->limit = $limit;
        $this->after = $afterInvitationId;
        return new InvitationPage([$this->record($scope)], 81);
    }
    public function read(PrincipalContext $principal, EventScope $scope, int $invitationId): InvitationRecord { return $this->record($scope); }
    public function update(PrincipalContext $principal, EventScope $scope, int $invitationId, InvitationPatch $patch, string $idempotencyKey): IdempotencyOutcome
    {
        $this->calls[] = 'update'; $this->keys[] = $idempotencyKey; $this->patch = $patch;
        return $this->outcome($this->record($scope, 5));
    }
    public function applyCompanionRollout(PrincipalContext $principal, EventScope $scope, string $idempotencyKey): IdempotencyOutcome
    {
        $this->calls[] = 'rollout'; $this->keys[] = $idempotencyKey;
        return new IdempotencyOutcome(false, new IdempotencyResultReference('invitation_rollout', $scope->eventId, 200), new CompanionRolloutResult(3));
    }
    public function archive(PrincipalContext $principal, EventScope $scope, int $invitationId, string $idempotencyKey): IdempotencyOutcome
    {
        $this->calls[] = 'archive'; $this->keys[] = $idempotencyKey;
        return $this->outcome($this->record($scope, 5, new DateTimeImmutable('2026-08-19T18:00:00Z')));
    }
    public function restore(PrincipalContext $principal, EventScope $scope, int $invitationId, string $idempotencyKey): IdempotencyOutcome
    {
        $this->calls[] = 'restore'; $this->keys[] = $idempotencyKey;
        return $this->outcome($this->record($scope, 6));
    }
    private function outcome(InvitationRecord $record): IdempotencyOutcome { return new IdempotencyOutcome(false, new IdempotencyResultReference('invitation', 81, 200), $record); }
    private function record(EventScope $scope, int $revision = 4, ?DateTimeImmutable $archivedAt = null): InvitationRecord
    {
        return new InvitationRecord(81, $scope, 'INVITE81', 'Guest', 4, InvitationStatus::REVOKED, 2, null, 'guest@example.test', '+1 555 0100', 'VIP', 'pending', $revision, $archivedAt);
    }
}

final readonly class InvitationAccessPrincipalResolver implements AuthenticatedPrincipalResolver
{
    public function resolve(RestRequest $request): PrincipalContext { return PrincipalContext::wordpressUser(7); }
}
final readonly class InvitationAccessRandom implements SecureRandom
{
    public function hex(int $bytes): string { return str_repeat('f', $bytes * 2); }
}
final class InvitationAccessMemoryRoutes implements RestRouteRegistry
{
    /** @var list<string> */ public array $registered = [];
    public function registerPublicGet(string $namespace, string $route, callable $handler): void {}
    public function registerPublicPost(string $namespace, string $route, callable $handler): void {}
    public function registerPublicPut(string $namespace, string $route, callable $handler): void {}
    public function registerAuthenticatedPost(string $namespace, string $route, callable $handler): void { $this->registered[] = 'POST ' . $namespace . $route; }
    public function registerAuthenticatedGet(string $namespace, string $route, callable $handler): void { $this->registered[] = 'GET ' . $namespace . $route; }
    public function registerAuthenticatedPatch(string $namespace, string $route, callable $handler): void { $this->registered[] = 'PATCH ' . $namespace . $route; }
}
