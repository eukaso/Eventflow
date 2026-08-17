<?php

namespace EventFlow\Presentation\Api;

final readonly class EventRouteRegistrar implements RestRouteRegistrar
{
    public function __construct(private EventController $controller)
    {
    }

    public function register(RestRouteRegistry $routes): void
    {
        $routes->registerAuthenticatedPost(SystemRouteRegistrar::NAMESPACE, '/events', $this->controller->create(...));
        foreach (EventLifecycleCommand::cases() as $command) {
            $routes->registerAuthenticatedPost(
                SystemRouteRegistrar::NAMESPACE,
                '/events/(?P<event_id>\d+)/' . $command->value,
                fn (RestRequest $request): ApiResponse => $this->controller->transition($request, $command),
            );
        }
    }
}
