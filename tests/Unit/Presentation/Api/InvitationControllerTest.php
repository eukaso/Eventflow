<?php

namespace EventFlow\Tests\Unit\Presentation\Api;

use DateTimeImmutable;
use EventFlow\Application\Authorization\PrincipalContext;
use EventFlow\Application\Error\RequestIdFactory;
use EventFlow\Application\Idempotency\{IdempotencyOutcome, IdempotencyResultReference};
use EventFlow\Application\Invitation\{CreateInvitation, InvitationCommands, InvitationRecord, InvitationStatus, IssuedInvitation};
use EventFlow\Application\Persistence\EventScope;
use EventFlow\Application\Security\SecureRandom;
use EventFlow\Presentation\Api\{ApiResponse, AuthenticatedPrincipalResolver, AuthenticatedRequestContextFactory, InvitationController, InvitationCredentialCommand, InvitationPresenter, InvitationRequestMapper, InvitationRouteRegistrar, RequestInputException, RestRequest, RestRouteRegistry};
use PHPUnit\Framework\TestCase;

final class InvitationControllerTest extends TestCase
{
    public function testRegistrarExposesOnlyAuthoritativeInvitationMutations(): void
    {
        $routes = new InvitationMemoryRoutes();
        (new InvitationRouteRegistrar($this->controller(new InvitationCommandPort())))->register($routes);
        self::assertSame([
            'POST eventflow/v1/events/(?P<event_id>\d+)/invitations',
            'POST eventflow/v1/events/(?P<event_id>\d+)/invitations/(?P<invitation_id>\d+)/activate',
            'POST eventflow/v1/events/(?P<event_id>\d+)/invitations/(?P<invitation_id>\d+)/rotate-token',
            'POST eventflow/v1/events/(?P<event_id>\d+)/invitations/(?P<invitation_id>\d+)/revoke',
        ], $routes->registered);
    }

    public function testCreateReturnsNormalizedInvitationAndReturnOnceCredential(): void
    {
        $port = new InvitationCommandPort();
        $response = $this->controller($port)->create(new RestRequest(
            ['Idempotency-Key' => 'invitation-create-001'],
            [
                'primary_name' => 'Guest Family', 'capacity' => 4,
                'primary_email' => 'guest@example.test', 'primary_phone' => '+1 555 0100',
                'token_expires_at' => '2027-01-02T03:04:05+00:00',
            ],
            ['event_id' => '44'],
        ));

        self::assertSame('create', $port->calls[0]);
        self::assertSame('invitation-create-001', $port->keys[0]);
        self::assertSame(201, $response->status());
        self::assertSame('Guest Family', $response->body()['data']['primary_name']);
        self::assertSame('2027-01-02T03:04:05Z', $response->body()['data']['token_expires_at']);
        self::assertTrue($response->body()['data']['credential']['return_once']);
        self::assertMatchesRegularExpression('/^[a-f0-9]{64}$/', $response->body()['data']['credential']['token']);
        self::assertSame('no-store, max-age=0', $response->headers()['Cache-Control']);
    }

    public function testCredentialCommandsAndRevokeDelegateExplicitOperations(): void
    {
        $port = new InvitationCommandPort();
        $controller = $this->controller($port);
        $route = ['event_id' => '44', 'invitation_id' => '81'];
        foreach (InvitationCredentialCommand::cases() as $command) {
            $controller->replaceCredential(new RestRequest(
                ['Idempotency-Key' => 'invitation-' . $command->value],
                ['token_expires_at' => null],
                $route,
            ), $command);
        }
        $response = $controller->revoke(new RestRequest(
            ['Idempotency-Key' => 'invitation-revoke-001'],
            routeParameters: $route,
        ));

        self::assertSame(['reactivate', 'rotate', 'revoke'], $port->calls);
        self::assertSame('revoked', $response->body()['data']['status']);
        self::assertArrayNotHasKey('credential', $response->body()['data']);
    }

    public function testInvalidInputsFailBeforeServiceInvocation(): void
    {
        $port = new InvitationCommandPort();
        foreach ([
            fn () => $this->controller($port)->create(new RestRequest(['Idempotency-Key'=>'invitation-invalid-001'], ['primary_name'=>'Guest','capacity'=>0], ['event_id'=>'44'])),
            fn () => $this->controller($port)->create(new RestRequest(['Idempotency-Key'=>'invitation-invalid-002'], ['primary_name'=>'Guest','admin'=>true], ['event_id'=>'44'])),
            fn () => $this->controller($port)->create(new RestRequest(['Idempotency-Key'=>'invitation-invalid-003'], ['primary_name'=>'Guest','token_expires_at'=>'2026-02-31T10:00:00Z'], ['event_id'=>'44'])),
            fn () => $this->controller($port)->replaceCredential(new RestRequest(['Idempotency-Key'=>'invitation-invalid-004'], ['unexpected'=>true], ['event_id'=>'44','invitation_id'=>'81']), InvitationCredentialCommand::ROTATE_TOKEN),
            fn () => $this->controller($port)->revoke(new RestRequest(['Idempotency-Key'=>'invitation-invalid-005'], ['unexpected'=>true], ['event_id'=>'44','invitation_id'=>'81'])),
            fn () => $this->controller($port)->revoke(new RestRequest(['Idempotency-Key'=>'invitation-invalid-006'], routeParameters:['event_id'=>'44','invitation_id'=>'../81'])),
        ] as $operation) {
            try { $operation(); self::fail('Expected controlled request failure.'); }
            catch (RequestInputException $failure) { self::assertContains($failure->safeCode, ['validation_failed', 'resource_not_found']); }
        }
        self::assertSame([], $port->calls);
    }

    private function controller(InvitationCommands $port): InvitationController
    {
        return new InvitationController(
            $port,
            new AuthenticatedRequestContextFactory(new InvitationPrincipalResolver(), new RequestIdFactory(new InvitationRequestRandom())),
            new InvitationRequestMapper(),
            new InvitationPresenter(),
        );
    }
}

final class InvitationMemoryRoutes implements RestRouteRegistry
{
    public array $registered = [];
    public function registerPublicGet(string $namespace, string $route, callable $handler): void {}
    public function registerPublicPost(string $namespace, string $route, callable $handler): void {}
    public function registerAuthenticatedPost(string $namespace, string $route, callable $handler): void { $this->registered[] = 'POST ' . $namespace . $route; }
    public function registerAuthenticatedPatch(string $namespace, string $route, callable $handler): void {}
}

final readonly class InvitationPrincipalResolver implements AuthenticatedPrincipalResolver
{
    public function resolve(RestRequest $request): PrincipalContext { return PrincipalContext::wordpressUser(7); }
}

final readonly class InvitationRequestRandom implements SecureRandom
{
    public function hex(int $bytes): string { return str_repeat('e', $bytes * 2); }
}

final class InvitationCommandPort implements InvitationCommands
{
    public array $calls = [];
    public array $keys = [];

    public function create(PrincipalContext $principal, CreateInvitation $command, string $idempotencyKey): IdempotencyOutcome
    {
        $this->calls[] = 'create'; $this->keys[] = $idempotencyKey;
        return $this->issued($command->eventScope, 81, 201, $command->primaryName, $command->capacity, $command->tokenExpiresAt);
    }

    public function rotateCredential(PrincipalContext $principal, EventScope $scope, int $invitationId, ?DateTimeImmutable $expiresAt, string $idempotencyKey): IdempotencyOutcome
    {
        $this->calls[] = 'rotate'; $this->keys[] = $idempotencyKey;
        return $this->issued($scope, $invitationId, 200, expiresAt: $expiresAt, version: 2);
    }

    public function reactivate(PrincipalContext $principal, EventScope $scope, int $invitationId, ?DateTimeImmutable $expiresAt, string $idempotencyKey): IdempotencyOutcome
    {
        $this->calls[] = 'reactivate'; $this->keys[] = $idempotencyKey;
        return $this->issued($scope, $invitationId, 200, expiresAt: $expiresAt, version: 2);
    }

    public function revoke(PrincipalContext $principal, EventScope $scope, int $invitationId, string $idempotencyKey): IdempotencyOutcome
    {
        $this->calls[] = 'revoke'; $this->keys[] = $idempotencyKey;
        $record = $this->record($scope, $invitationId, status: InvitationStatus::REVOKED);
        return new IdempotencyOutcome(false, new IdempotencyResultReference('invitation', $invitationId, 200), $record);
    }

    private function issued(EventScope $scope, int $id, int $status, string $name = 'Guest Family', int $capacity = 4, ?DateTimeImmutable $expiresAt = null, int $version = 1): IdempotencyOutcome
    {
        $record = $this->record($scope, $id, $name, $capacity, $expiresAt, version: $version);
        return new IdempotencyOutcome(false, new IdempotencyResultReference('invitation', $id, $status), new IssuedInvitation($record, str_repeat('a', 64)));
    }

    private function record(EventScope $scope, int $id, string $name = 'Guest Family', int $capacity = 4, ?DateTimeImmutable $expiresAt = null, InvitationStatus $status = InvitationStatus::ACTIVE, int $version = 1): InvitationRecord
    {
        return new InvitationRecord($id, $scope, 'INVITE01', $name, $capacity, $status, $version, $expiresAt);
    }
}
