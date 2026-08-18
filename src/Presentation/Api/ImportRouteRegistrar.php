<?php

namespace EventFlow\Presentation\Api;

final readonly class ImportRouteRegistrar implements RestRouteRegistrar
{
    public function __construct(private ImportController $controller) {}

    public function register(RestRouteRegistry $routes): void
    {
        $routes->registerAuthenticatedPost(
            SystemRouteRegistrar::NAMESPACE,
            '/events/(?P<event_id>\d+)/imports/(?P<import_job_id>\d+)/validate',
            $this->controller->validate(...),
        );
    }
}
