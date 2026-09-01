<?php

namespace EventFlow\Presentation\Api;

final readonly class CheckInRouteRegistrar implements RestRouteRegistrar
{
    public function __construct(private CheckInController $controller) {}

    public function register(RestRouteRegistry $routes): void
    {
        $event = '/events/(?P<event_id>\d+)';
        $routes->registerAuthenticatedGet(SystemRouteRegistrar::NAMESPACE, $event . '/reception/attendees', $this->controller->search(...));
        $routes->registerAuthenticatedGet(SystemRouteRegistrar::NAMESPACE, $event . '/reception/lookup', $this->controller->lookup(...));
        $routes->registerAuthenticatedPost(SystemRouteRegistrar::NAMESPACE, $event . '/check-ins', $this->controller->checkIn(...));
        $routes->registerAuthenticatedPost(SystemRouteRegistrar::NAMESPACE, $event . '/check-ins/bulk', $this->controller->bulk(...));
        $routes->registerAuthenticatedPost(SystemRouteRegistrar::NAMESPACE, $event . '/check-ins/(?P<checkin_id>\d+)/reverse', $this->controller->reverse(...));
    }
}
