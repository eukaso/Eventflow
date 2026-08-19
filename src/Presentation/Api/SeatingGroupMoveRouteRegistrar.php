<?php

namespace EventFlow\Presentation\Api;

final readonly class SeatingGroupMoveRouteRegistrar implements RestRouteRegistrar
{
    public function __construct(private SeatingGroupMoveController $controller) {}

    public function register(RestRouteRegistry $routes): void
    {
        $routes->registerAuthenticatedPost(
            SystemRouteRegistrar::NAMESPACE,
            '/events/(?P<event_id>\d+)/seating-groups/(?P<group_id>\d+)/move',
            $this->controller->move(...),
        );
    }
}
