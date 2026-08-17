<?php

namespace EventFlow\Tests\Unit\Presentation\Api;

use DateTimeImmutable;
use DateTimeZone;
use EventFlow\Application\Error\RequestIdFactory;
use EventFlow\Application\GuestAccess\{GuestCredentialType, GuestSessionBootstrap, GuestSessionCredentials, GuestSessionRecord};
use EventFlow\Application\Persistence\EventScope;
use EventFlow\Application\Security\SecureRandom;
use EventFlow\Presentation\Api\{ApiResponse, GuestBootstrapController, GuestBootstrapRequestMapper, GuestBootstrapRouteRegistrar, GuestSessionPresenter, PublicBootstrapRateLimiter, RequestInputException, RestRequest, RestRouteRegistry};
use PHPUnit\Framework\TestCase;

final class GuestBootstrapControllerTest extends TestCase
{
    public function testRegistrarExposesOnePublicPostRoute(): void
    {
        $routes = new GuestBootstrapMemoryRoutes();
        (new GuestBootstrapRouteRegistrar($this->controller(new GuestBootstrapPort(), new GuestBootstrapLimiter())))->register($routes);
        self::assertSame(['POST eventflow/v1/public/invitations/bootstrap'], $routes->registered);
    }

    public function testBootstrapThrottlesBeforeServiceAndKeepsSessionTokenOutOfBody(): void
    {
        $port = new GuestBootstrapPort();
        $limiter = new GuestBootstrapLimiter();
        $credential = str_repeat('b', 64);
        $response = $this->controller($port, $limiter)->bootstrap(new RestRequest(
            ['X-Request-ID' => 'req_0123456789abcdef0123456789abcdef'],
            ['credential' => $credential],
            trustedClientAddress: '203.0.113.7',
        ));

        self::assertSame([GuestCredentialType::INVITATION], $port->types);
        self::assertSame([$credential], $port->credentials);
        self::assertSame('203.0.113.7', $limiter->address);
        self::assertSame(hash('sha256', $credential), $limiter->fingerprint);
        self::assertSame(201, $response->status());
        self::assertSame(str_repeat('d', 64), $response->body()['data']['csrf_token']);
        self::assertStringNotContainsString(str_repeat('c', 64), json_encode($response->body(), JSON_THROW_ON_ERROR));
        self::assertStringContainsString('eventflow_guest_session=' . str_repeat('c', 64), $response->headers()['Set-Cookie']);
        self::assertStringContainsString('Secure; HttpOnly; SameSite=Lax', $response->headers()['Set-Cookie']);
    }

    public function testMalformedAndUnknownFieldsUseGenericFailureBeforeRateLimiter(): void
    {
        $port = new GuestBootstrapPort();
        $limiter = new GuestBootstrapLimiter();
        foreach ([
            ['credential' => 'short'],
            ['credential' => str_repeat('b', 64), 'type' => 'message_link'],
        ] as $json) {
            try { $this->controller($port, $limiter)->bootstrap(new RestRequest(json: $json)); self::fail('Expected failure.'); }
            catch (RequestInputException $failure) { self::assertContains($failure->safeCode, ['guest_session_invalid', 'validation_failed']); }
        }
        self::assertSame([], $port->credentials);
        self::assertSame(0, $limiter->calls);
    }

    private function controller(GuestSessionBootstrap $port, PublicBootstrapRateLimiter $limiter): GuestBootstrapController
    {
        return new GuestBootstrapController(
            $port,
            $limiter,
            new RequestIdFactory(new GuestBootstrapRandom()),
            new GuestBootstrapRequestMapper(),
            new GuestSessionPresenter(),
        );
    }
}

final class GuestBootstrapMemoryRoutes implements RestRouteRegistry
{
    public array $registered = [];
    public function registerPublicGet(string $namespace, string $route, callable $handler): void {}
    public function registerPublicPost(string $namespace, string $route, callable $handler): void { $this->registered[] = 'POST ' . $namespace . $route; }
    public function registerAuthenticatedPost(string $namespace, string $route, callable $handler): void {}
    public function registerAuthenticatedPatch(string $namespace, string $route, callable $handler): void {}
}

final class GuestBootstrapPort implements GuestSessionBootstrap
{
    public array $credentials = [];
    public array $types = [];
    public function bootstrap(string $rawCredential, GuestCredentialType $type): GuestSessionCredentials
    {
        $this->credentials[] = $rawCredential;
        $this->types[] = $type;
        return new GuestSessionCredentials(
            new GuestSessionRecord(9, new EventScope(44), 81, 2, str_repeat('x', 32), new DateTimeImmutable('2026-08-18 02:00:00', new DateTimeZone('UTC'))),
            str_repeat('c', 64),
            str_repeat('d', 64),
        );
    }
}

final class GuestBootstrapLimiter implements PublicBootstrapRateLimiter
{
    public int $calls = 0;
    public ?string $address = null;
    public ?string $fingerprint = null;
    public function consume(?string $clientAddress, string $credentialFingerprint): void
    {
        $this->calls++;
        $this->address = $clientAddress;
        $this->fingerprint = $credentialFingerprint;
    }
}

final readonly class GuestBootstrapRandom implements SecureRandom
{
    public function hex(int $bytes): string { return str_repeat('f', $bytes * 2); }
}
