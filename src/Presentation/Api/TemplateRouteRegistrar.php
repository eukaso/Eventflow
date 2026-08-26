<?php

namespace EventFlow\Presentation\Api;

final readonly class TemplateRouteRegistrar implements RestRouteRegistrar
{
    public function __construct(private TemplateController $controller) {}

    public function register(RestRouteRegistry $routes): void
    {
        $collection = '/events/(?P<event_id>\d+)/communication-templates';
        $routes->registerAuthenticatedPost(SystemRouteRegistrar::NAMESPACE, $collection, $this->controller->create(...));
        $routes->registerAuthenticatedPost(
            SystemRouteRegistrar::NAMESPACE,
            $collection . '/(?P<template_id>\d+)/publish',
            $this->controller->publish(...),
        );
        $routes->registerAuthenticatedPost(
            SystemRouteRegistrar::NAMESPACE,
            $collection . '/(?P<template_id>\d+)/activate',
            $this->controller->publish(...),
        );
    }
}
