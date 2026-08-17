<?php

namespace EventFlow\Tests\Unit\Presentation\Api;

use EventFlow\Application\Health\SystemHealthService;
use EventFlow\Bootstrap\{BootstrapResult, BootstrapState, Container};
use EventFlow\Infrastructure\Config\Config;
use EventFlow\Infrastructure\Health\BootstrapReadinessCheck;
use EventFlow\Presentation\Api\{ApiResponse, RestRequest, RestRouteRegistry, SystemRouteRegistrar, SystemStatusController, SystemStatusPresenter};
use PHPUnit\Framework\TestCase;

final class SystemRoutesTest extends TestCase
{
    public function testRegistrarPublishesOnlyTheTwoPublicGetSystemRoutes(): void
    {
        $bootstrap = new BootstrapResult(BootstrapState::READY, true, true);
        $routes = new MemoryRestRoutes();
        (new SystemRouteRegistrar($this->controller($bootstrap)))->register($routes);

        self::assertSame([
            'eventflow/v1/system/health',
            'eventflow/v1/system/readiness',
        ], array_keys($routes->handlers));
        self::assertSame(['GET', 'GET'], array_values($routes->methods));
    }

    public function testHealthPreservesValidRequestIdAndReplacesInvalidInput(): void
    {
        $routes = new MemoryRestRoutes();
        (new SystemRouteRegistrar($this->controller(new BootstrapResult(BootstrapState::READY, true, true))))->register($routes);

        $valid = 'req_0123456789abcdef0123456789abcdef';
        $response = $routes->call('/system/health', new RestRequest(['X-Request-ID' => $valid]));
        self::assertSame(200, $response->status());
        self::assertSame($valid, $response->headers()['X-Request-ID']);
        self::assertSame('no-store, max-age=0', $response->headers()['Cache-Control']);

        $replacement = $routes->call('/system/health', new RestRequest(['X-Request-ID' => "bad\r\nInjected: yes"]));
        self::assertMatchesRegularExpression('/^req_[a-f0-9]{32}$/', $replacement->headers()['X-Request-ID']);
        self::assertStringNotContainsString('Injected', $replacement->headers()['X-Request-ID']);
    }

    public function testMinimalModeStillExposesHealthAndBlockedReadiness(): void
    {
        $bootstrap = new BootstrapResult(BootstrapState::MIGRATION_REQUIRED, true, false, ['schema_migration_required']);
        $routes = new MemoryRestRoutes();
        (new SystemRouteRegistrar($this->controller($bootstrap)))->register($routes);

        self::assertSame(200, $routes->call('/system/health')->status());
        $readiness = $routes->call('/system/readiness');
        self::assertSame(503, $readiness->status());
        self::assertFalse($readiness->body()['ready']);
        self::assertSame('schema_migration_required', $readiness->body()['checks'][0]['code']);
    }

    private function controller(BootstrapResult $bootstrap): SystemStatusController
    {
        $container = Container::createFoundation(new Config('testing', '0.9.0', 6, 'error', false));
        $health = new SystemHealthService(
            $bootstrap,
            [new BootstrapReadinessCheck($bootstrap)],
            $container->services->clock,
            $container->config->pluginVersion,
        );
        return new SystemStatusController(
            $health,
            new SystemStatusPresenter(),
            $container->services->requestIds,
            $container->services->apiErrors,
        );
    }
}

final class MemoryRestRoutes implements RestRouteRegistry
{
    /** @var array<string, callable(RestRequest):ApiResponse> */
    public array $handlers = [];
    /** @var array<string, string> */
    public array $methods = [];

    public function registerPublicGet(string $namespace, string $route, callable $handler): void
    {
        $key = $namespace . $route;
        $this->handlers[$key] = $handler;
        $this->methods[$key] = 'GET';
    }

    public function registerPublicPost(string $namespace, string $route, callable $handler): void
    {
        $key = $namespace . $route;
        $this->handlers[$key] = $handler;
        $this->methods[$key] = 'POST';
    }

    public function registerPublicPut(string $namespace, string $route, callable $handler): void
    {
        $key = $namespace . $route;
        $this->handlers[$key] = $handler;
        $this->methods[$key] = 'PUT';
    }

    public function registerAuthenticatedPost(string $namespace, string $route, callable $handler): void
    {
        $key = $namespace . $route;
        $this->handlers[$key] = $handler;
        $this->methods[$key] = 'POST';
    }

    public function registerAuthenticatedPatch(string $namespace, string $route, callable $handler): void
    {
        $key = $namespace . $route;
        $this->handlers[$key] = $handler;
        $this->methods[$key] = 'PATCH';
    }

    public function call(string $route, ?RestRequest $request = null): ApiResponse
    {
        return $this->handlers['eventflow/v1' . $route]($request ?? new RestRequest());
    }
}
