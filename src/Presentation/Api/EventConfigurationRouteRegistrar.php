<?php
namespace EventFlow\Presentation\Api;
final readonly class EventConfigurationRouteRegistrar implements RestRouteRegistrar
{
    public function __construct(private EventConfigurationController $controller){}
    public function register(RestRouteRegistry $routes):void{$route='/events/(?P<event_id>\d+)/configuration';$routes->registerAuthenticatedGet(SystemRouteRegistrar::NAMESPACE,$route,$this->controller->read(...));$routes->registerAuthenticatedPatch(SystemRouteRegistrar::NAMESPACE,$route,$this->controller->update(...));}
}
