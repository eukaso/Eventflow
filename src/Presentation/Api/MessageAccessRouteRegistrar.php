<?php

namespace EventFlow\Presentation\Api;

final readonly class MessageAccessRouteRegistrar implements RestRouteRegistrar
{
    public function __construct(private MessageAccessController$controller){}
    public function register(RestRouteRegistry$routes):void{$collection='/events/(?P<event_id>\d+)/messages';$resource=$collection.'/(?P<message_id>\d+)';$routes->registerAuthenticatedGet(SystemRouteRegistrar::NAMESPACE,$collection,$this->controller->list(...));$routes->registerAuthenticatedGet(SystemRouteRegistrar::NAMESPACE,$resource,$this->controller->read(...));$routes->registerAuthenticatedPost(SystemRouteRegistrar::NAMESPACE,$resource.'/retry',$this->controller->retry(...));}
}
