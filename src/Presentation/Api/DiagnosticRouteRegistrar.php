<?php

namespace EventFlow\Presentation\Api;

final readonly class DiagnosticRouteRegistrar implements RestRouteRegistrar
{
    public function __construct(private DiagnosticController $controller) {}

    public function register(RestRouteRegistry $routes): void
    {
        $routes->registerAuthenticatedGet(
            SystemRouteRegistrar::NAMESPACE,
            '/events/(?P<event_id>\d+)/diagnostics',
            $this->controller->export(...),
        );
    }
}
