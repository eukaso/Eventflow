<?php

namespace EventFlow\Presentation\Api;

final readonly class GuestBootstrapRouteRegistrar implements RestRouteRegistrar
{
    public function __construct(private GuestBootstrapController $controller)
    {
    }

    public function register(RestRouteRegistry $routes): void
    {
        $routes->registerPublicPost(
            SystemRouteRegistrar::NAMESPACE,
            '/public/invitations/bootstrap',
            $this->controller->bootstrap(...),
        );
    }
}
