<?php

namespace EventFlow\Presentation\Api;

final readonly class ExportRouteRegistrar implements RestRouteRegistrar
{
    public function __construct(private ExportController $controller) {}

    public function register(RestRouteRegistry $routes): void
    {
        $collection = '/events/(?P<event_id>\d+)/exports';
        $resource = $collection.'/(?P<export_id>\d+)';
        $routes->registerAuthenticatedGet(SystemRouteRegistrar::NAMESPACE, $collection, $this->controller->list(...));
        $routes->registerAuthenticatedPost(SystemRouteRegistrar::NAMESPACE, $collection, $this->controller->create(...));
        $routes->registerAuthenticatedGet(SystemRouteRegistrar::NAMESPACE, $resource, $this->controller->read(...));
        $routes->registerAuthenticatedGet(SystemRouteRegistrar::NAMESPACE, $resource.'/download', $this->controller->download(...));
    }
}
