<?php

namespace EventFlow\Presentation\Api;

final readonly class CampaignRouteRegistrar implements RestRouteRegistrar
{
    public function __construct(private CampaignController $controller) {}

    public function register(RestRouteRegistry $routes): void
    {
        $collection = '/events/(?P<event_id>\d+)/campaigns';
        $routes->registerAuthenticatedPost(SystemRouteRegistrar::NAMESPACE, $collection, $this->controller->create(...));
        $routes->registerAuthenticatedPost(
            SystemRouteRegistrar::NAMESPACE,
            $collection . '/(?P<campaign_id>\d+)/queue',
            $this->controller->queue(...),
        );
    }
}
