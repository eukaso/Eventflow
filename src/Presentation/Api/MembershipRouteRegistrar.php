<?php

namespace EventFlow\Presentation\Api;

final readonly class MembershipRouteRegistrar implements RestRouteRegistrar
{
    public function __construct(private MembershipController $controller)
    {
    }

    public function register(RestRouteRegistry $routes): void
    {
        $collection = '/events/(?P<event_id>\d+)/memberships';
        $member = $collection . '/(?P<membership_id>\d+)';
        $routes->registerAuthenticatedPost(SystemRouteRegistrar::NAMESPACE, $collection, $this->controller->grant(...));
        $routes->registerAuthenticatedPatch(SystemRouteRegistrar::NAMESPACE, $member, $this->controller->change(...));
        foreach (MembershipCommand::cases() as $command) {
            $routes->registerAuthenticatedPost(
                SystemRouteRegistrar::NAMESPACE,
                $member . '/' . $command->value,
                fn (RestRequest $request): ApiResponse => $this->controller->transition($request, $command),
            );
        }
        $routes->registerAuthenticatedPost(
            SystemRouteRegistrar::NAMESPACE,
            $member . '/make-primary-owner',
            $this->controller->makePrimaryOwner(...),
        );
    }
}
