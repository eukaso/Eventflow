<?php

namespace EventFlow\Presentation\Api;

final readonly class InvitationAccessRouteRegistrar implements RestRouteRegistrar
{
    public function __construct(private InvitationAccessController $controller)
    {
    }

    public function register(RestRouteRegistry $routes): void
    {
        $collection = '/events/(?P<event_id>\d+)/invitations';
        $member = $collection . '/(?P<invitation_id>\d+)';
        $routes->registerAuthenticatedGet(SystemRouteRegistrar::NAMESPACE, $collection, $this->controller->list(...));
        $routes->registerAuthenticatedGet(SystemRouteRegistrar::NAMESPACE, $member, $this->controller->read(...));
        $routes->registerAuthenticatedPatch(SystemRouteRegistrar::NAMESPACE, $member, $this->controller->update(...));
        foreach (InvitationAccessCommand::cases() as $command) {
            $routes->registerAuthenticatedPost(
                SystemRouteRegistrar::NAMESPACE,
                $member . '/' . $command->value,
                fn (RestRequest $request): ApiResponse => $this->controller->transition($request, $command),
            );
        }
    }
}
