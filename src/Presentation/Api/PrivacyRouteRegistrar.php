<?php

namespace EventFlow\Presentation\Api;

final readonly class PrivacyRouteRegistrar implements RestRouteRegistrar
{
    public function __construct(private PrivacyController$controller){}
    public function register(RestRouteRegistry$routes):void
    {
        $actions='/events/(?P<event_id>\d+)/privacy-actions';$action=$actions.'/(?P<privacy_action_id>\d+)';
        $holds='/events/(?P<event_id>\d+)/retention-holds';$hold=$holds.'/(?P<retention_hold_id>\d+)';
        $routes->registerAuthenticatedGet(SystemRouteRegistrar::NAMESPACE,$actions,$this->controller->listActions(...));
        $routes->registerAuthenticatedPost(SystemRouteRegistrar::NAMESPACE,$actions,$this->controller->createAction(...));
        $routes->registerAuthenticatedGet(SystemRouteRegistrar::NAMESPACE,$action,$this->controller->readAction(...));
        $routes->registerAuthenticatedGet(SystemRouteRegistrar::NAMESPACE,$holds,$this->controller->listHolds(...));
        $routes->registerAuthenticatedPost(SystemRouteRegistrar::NAMESPACE,$holds,$this->controller->createHold(...));
        $routes->registerAuthenticatedGet(SystemRouteRegistrar::NAMESPACE,$hold,$this->controller->readHold(...));
        $routes->registerAuthenticatedPost(SystemRouteRegistrar::NAMESPACE,$hold.'/release',$this->controller->releaseHold(...));
    }
}
