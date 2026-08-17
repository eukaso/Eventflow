<?php

namespace EventFlow\Presentation\Api;

final readonly class RsvpRouteRegistrar implements RestRouteRegistrar
{
    public function __construct(private RsvpController $controller)
    {
    }

    public function register(RestRouteRegistry $routes): void
    {
        $routes->registerPublicPut(
            SystemRouteRegistrar::NAMESPACE,
            '/public/invitation/response',
            $this->controller->submit(...),
        );
    }
}
