<?php

namespace EventFlow\Presentation\Api;

final readonly class SystemRouteRegistrar
{
    public const NAMESPACE = 'eventflow/v1';

    public function __construct(private SystemStatusController $controller)
    {
    }

    public function register(RestRouteRegistry $routes): void
    {
        $routes->registerPublicGet(self::NAMESPACE, '/system/health', $this->controller->health(...));
        $routes->registerPublicGet(self::NAMESPACE, '/system/readiness', $this->controller->readiness(...));
    }
}
