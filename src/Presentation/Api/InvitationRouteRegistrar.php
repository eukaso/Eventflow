<?php

namespace EventFlow\Presentation\Api;

final readonly class InvitationRouteRegistrar implements RestRouteRegistrar
{
    public function __construct(private InvitationController $controller)
    {
    }

    public function register(RestRouteRegistry $routes): void
    {
        $collection = '/events/(?P<event_id>\d+)/invitations';
        $member = $collection . '/(?P<invitation_id>\d+)';
        $routes->registerAuthenticatedPost(SystemRouteRegistrar::NAMESPACE, $collection, $this->controller->create(...));
        foreach (InvitationCredentialCommand::cases() as $command) {
            $routes->registerAuthenticatedPost(
                SystemRouteRegistrar::NAMESPACE,
                $member . '/' . $command->value,
                fn (RestRequest $request): ApiResponse => $this->controller->replaceCredential($request, $command),
            );
        }
        $routes->registerAuthenticatedPost(SystemRouteRegistrar::NAMESPACE, $member . '/revoke', $this->controller->revoke(...));
    }
}
