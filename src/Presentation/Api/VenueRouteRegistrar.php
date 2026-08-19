<?php
namespace EventFlow\Presentation\Api;
final readonly class VenueRouteRegistrar implements RestRouteRegistrar
{
    public function __construct(private VenueController $controller){}
    public function register(RestRouteRegistry $routes):void{$member='/venues/(?P<venue_id>\d+)';$routes->registerAuthenticatedGet(SystemRouteRegistrar::NAMESPACE,'/venues',$this->controller->list(...));$routes->registerAuthenticatedPost(SystemRouteRegistrar::NAMESPACE,'/venues',$this->controller->create(...));$routes->registerAuthenticatedGet(SystemRouteRegistrar::NAMESPACE,$member,$this->controller->read(...));$routes->registerAuthenticatedPatch(SystemRouteRegistrar::NAMESPACE,$member,$this->controller->update(...));}
}
