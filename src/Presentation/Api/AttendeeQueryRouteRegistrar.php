<?php

namespace EventFlow\Presentation\Api;

final readonly class AttendeeQueryRouteRegistrar implements RestRouteRegistrar
{
    public function __construct(private AttendeeQueryController $controller)
    {
    }

    public function register(RestRouteRegistry $routes): void
    {
        $collection = '/events/(?P<event_id>\d+)/attendees';
        $routes->registerAuthenticatedGet(SystemRouteRegistrar::NAMESPACE, $collection, $this->controller->list(...));
        $routes->registerAuthenticatedGet(
            SystemRouteRegistrar::NAMESPACE,
            $collection . '/(?P<attendee_id>\d+)',
            $this->controller->read(...),
        );
    }
}
