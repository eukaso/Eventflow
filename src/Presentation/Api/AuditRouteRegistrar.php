<?php

namespace EventFlow\Presentation\Api;

final readonly class AuditRouteRegistrar implements RestRouteRegistrar
{
    public function __construct(private AuditController $controller) {}

    public function register(RestRouteRegistry $routes): void
    {
        $collection = '/events/(?P<event_id>\d+)/audit';
        $routes->registerAuthenticatedGet(SystemRouteRegistrar::NAMESPACE, $collection, $this->controller->list(...));
        $routes->registerAuthenticatedGet(SystemRouteRegistrar::NAMESPACE, $collection.'/integrity', $this->controller->integrity(...));
        $routes->registerAuthenticatedGet(SystemRouteRegistrar::NAMESPACE, $collection.'/(?P<audit_log_id>\d+)', $this->controller->read(...));
    }
}
