<?php

namespace EventFlow\Presentation\Api;

final readonly class SeatingPlanningRouteRegistrar implements RestRouteRegistrar
{
    public function __construct(private SeatingPlanningController $controller)
    {
    }

    public function register(RestRouteRegistry $routes): void
    {
        $event = '/events/(?P<event_id>\d+)';
        $routes->registerAuthenticatedPost(SystemRouteRegistrar::NAMESPACE, $event . '/seating/recommendations', $this->controller->recommend(...));
        $routes->registerAuthenticatedPost(
            SystemRouteRegistrar::NAMESPACE,
            $event . '/attendees/(?P<attendee_id>\d+)/seating/move',
            $this->controller->move(...),
        );
    }
}
