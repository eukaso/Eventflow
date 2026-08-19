<?php

namespace EventFlow\Presentation\Api;

final readonly class GuestSessionAccessRouteRegistrar implements RestRouteRegistrar
{
    public function __construct(private GuestSessionAccessController $controller)
    {
    }

    public function register(RestRouteRegistry $routes): void
    {
        $routes->registerPublicGet(SystemRouteRegistrar::NAMESPACE, '/public/invitation', $this->controller->context(...));
        $routes->registerPublicGet(SystemRouteRegistrar::NAMESPACE, '/public/invitation/response', $this->controller->response(...));
        $routes->registerPublicPost(SystemRouteRegistrar::NAMESPACE, '/public/session/logout', $this->controller->logout(...));
    }
}
