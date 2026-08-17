<?php

namespace EventFlow\Presentation\Api;

final readonly class SeatingPreparationRouteRegistrar implements RestRouteRegistrar
{
    public function __construct(private SeatingPreparationController $controller)
    {
    }

    public function register(RestRouteRegistry $routes): void
    {
        $event = '/events/(?P<event_id>\d+)';
        $routes->registerAuthenticatedPost(SystemRouteRegistrar::NAMESPACE, $event . '/tables', $this->controller->createTable(...));
        $routes->registerAuthenticatedPost(SystemRouteRegistrar::NAMESPACE, $event . '/seating-groups', $this->controller->createGroup(...));
        $routes->registerAuthenticatedGet(SystemRouteRegistrar::NAMESPACE, $event . '/seating/readiness', $this->controller->readiness(...));
    }
}
