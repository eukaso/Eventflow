<?php

namespace EventFlow\Presentation\Api;

final readonly class MembershipQueryRouteRegistrar implements RestRouteRegistrar
{
    public function __construct(private MembershipQueryController $controller)
    {
    }

    public function register(RestRouteRegistry $routes): void
    {
        $routes->registerAuthenticatedGet(
            SystemRouteRegistrar::NAMESPACE,
            '/events/(?P<event_id>\d+)/memberships',
            $this->controller->list(...),
        );
    }
}
