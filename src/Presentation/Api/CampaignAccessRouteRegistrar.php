<?php

namespace EventFlow\Presentation\Api;

final readonly class CampaignAccessRouteRegistrar implements RestRouteRegistrar
{
    public function __construct(private CampaignAccessController $controller) {}

    public function register(RestRouteRegistry $routes): void
    {
        $collection='/events/(?P<event_id>\d+)/campaigns';
        $resource=$collection.'/(?P<campaign_id>\d+)';
        $routes->registerAuthenticatedGet(SystemRouteRegistrar::NAMESPACE,$collection,$this->controller->list(...));
        $routes->registerAuthenticatedGet(SystemRouteRegistrar::NAMESPACE,$resource,$this->controller->read(...));
        $routes->registerAuthenticatedPatch(SystemRouteRegistrar::NAMESPACE,$resource,$this->controller->update(...));
        $routes->registerAuthenticatedPost(SystemRouteRegistrar::NAMESPACE,$resource.'/audience-preview',$this->controller->audiencePreview(...));
        $routes->registerAuthenticatedPost(SystemRouteRegistrar::NAMESPACE,$resource.'/schedule',$this->controller->schedule(...));
        $routes->registerAuthenticatedPost(SystemRouteRegistrar::NAMESPACE,$resource.'/cancel',$this->controller->cancel(...));
    }
}
