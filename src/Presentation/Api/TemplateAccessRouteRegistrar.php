<?php

namespace EventFlow\Presentation\Api;

final readonly class TemplateAccessRouteRegistrar implements RestRouteRegistrar
{
    public function __construct(private TemplateAccessController $controller){}
    public function register(RestRouteRegistry $routes):void{$collection='/events/(?P<event_id>\d+)/communication-templates';$resource=$collection.'/(?P<template_id>\d+)';$routes->registerAuthenticatedGet(SystemRouteRegistrar::NAMESPACE,$collection,$this->controller->list(...));$routes->registerAuthenticatedGet(SystemRouteRegistrar::NAMESPACE,$resource,$this->controller->read(...));$routes->registerAuthenticatedPatch(SystemRouteRegistrar::NAMESPACE,$resource,$this->controller->update(...));$routes->registerAuthenticatedPost(SystemRouteRegistrar::NAMESPACE,$resource.'/new-version',$this->controller->newVersion(...));$routes->registerAuthenticatedPost(SystemRouteRegistrar::NAMESPACE,$resource.'/archive',$this->controller->archive(...));$routes->registerAuthenticatedPost(SystemRouteRegistrar::NAMESPACE,$resource.'/preview',$this->controller->preview(...));}
}
