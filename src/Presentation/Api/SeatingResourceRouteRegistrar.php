<?php

namespace EventFlow\Presentation\Api;

final readonly class SeatingResourceRouteRegistrar implements RestRouteRegistrar
{
    public function __construct(private SeatingResourceController $controller) {}

    public function register(RestRouteRegistry $routes): void
    {
        $event = '/events/(?P<event_id>\d+)';
        $table = $event . '/tables/(?P<table_id>\d+)';
        $seat = $table . '/seats/(?P<seat_id>\d+)';
        $group = $event . '/seating-groups/(?P<group_id>\d+)';
        $routes->registerAuthenticatedGet(SystemRouteRegistrar::NAMESPACE, $event . '/tables', $this->controller->listTables(...));
        $routes->registerAuthenticatedGet(SystemRouteRegistrar::NAMESPACE, $table, $this->controller->table(...));
        $routes->registerAuthenticatedPatch(SystemRouteRegistrar::NAMESPACE, $table, $this->controller->updateTable(...));
        $routes->registerAuthenticatedGet(SystemRouteRegistrar::NAMESPACE, $table . '/seats', $this->controller->listSeats(...));
        $routes->registerAuthenticatedPost(SystemRouteRegistrar::NAMESPACE, $table . '/seats', $this->controller->createSeat(...));
        $routes->registerAuthenticatedGet(SystemRouteRegistrar::NAMESPACE, $seat, $this->controller->seat(...));
        $routes->registerAuthenticatedPatch(SystemRouteRegistrar::NAMESPACE, $seat, $this->controller->updateSeat(...));
        $routes->registerAuthenticatedGet(SystemRouteRegistrar::NAMESPACE, $event . '/seating-groups', $this->controller->listGroups(...));
        $routes->registerAuthenticatedGet(SystemRouteRegistrar::NAMESPACE, $group, $this->controller->group(...));
        $routes->registerAuthenticatedPatch(SystemRouteRegistrar::NAMESPACE, $group, $this->controller->updateGroup(...));
    }
}
