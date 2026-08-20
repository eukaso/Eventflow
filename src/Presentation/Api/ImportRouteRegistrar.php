<?php

namespace EventFlow\Presentation\Api;

final readonly class ImportRouteRegistrar implements RestRouteRegistrar
{
    public function __construct(private ImportController $controller) {}

    public function register(RestRouteRegistry $routes): void
    {
        $mapping = '/events/(?P<event_id>\d+)/imports/(?P<import_job_id>\d+)/mapping';
        if (!method_exists($routes, 'registerAuthenticatedPut')) throw new \RuntimeException('authenticated_put_unavailable');
        $routes->registerAuthenticatedPut(SystemRouteRegistrar::NAMESPACE, $mapping, $this->controller->validate(...));
        $routes->registerAuthenticatedPost(
            SystemRouteRegistrar::NAMESPACE,
            '/events/(?P<event_id>\d+)/imports/(?P<import_job_id>\d+)/validate',
            $this->controller->validate(...),
        );
    }
}
