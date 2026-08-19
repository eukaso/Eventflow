<?php

namespace EventFlow\Presentation\Api;

final readonly class EventRouteRegistrar implements RestRouteRegistrar
{
    public function __construct(private EventController $controller)
    {
    }

    public function register(RestRouteRegistry $routes): void
    {
        $member = '/events/(?P<event_id>\d+)';
        $routes->registerAuthenticatedGet(SystemRouteRegistrar::NAMESPACE, '/events', $this->controller->list(...));
        $routes->registerAuthenticatedGet(SystemRouteRegistrar::NAMESPACE, $member, $this->controller->read(...));
        $routes->registerAuthenticatedPost(SystemRouteRegistrar::NAMESPACE, '/events', $this->controller->create(...));
        $routes->registerAuthenticatedPatch(SystemRouteRegistrar::NAMESPACE, $member, $this->controller->update(...));
        foreach (EventLifecycleCommand::cases() as $command) {
            $routes->registerAuthenticatedPost(
                SystemRouteRegistrar::NAMESPACE,
                '/events/(?P<event_id>\d+)/' . $command->value,
                fn (RestRequest $request): ApiResponse => $this->controller->transition($request, $command),
            );
        }
    }
}
