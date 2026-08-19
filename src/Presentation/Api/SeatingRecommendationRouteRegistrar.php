<?php

namespace EventFlow\Presentation\Api;

final readonly class SeatingRecommendationRouteRegistrar implements RestRouteRegistrar
{
    public function __construct(private SeatingRecommendationController $controller) {}

    public function register(RestRouteRegistry $routes): void
    {
        $collection = '/events/(?P<event_id>\d+)/seating/recommendations';
        $resource = $collection . '/(?P<recommendation_id>\d+)';
        $routes->registerAuthenticatedPost(SystemRouteRegistrar::NAMESPACE, $collection, $this->controller->generate(...));
        $routes->registerAuthenticatedGet(SystemRouteRegistrar::NAMESPACE, $resource, $this->controller->get(...));
        $routes->registerAuthenticatedPost(SystemRouteRegistrar::NAMESPACE, $resource . '/apply', $this->controller->apply(...));
    }
}
