<?php

namespace EventFlow\Presentation\Api;

final readonly class AttendeeRouteRegistrar implements RestRouteRegistrar
{
    public function __construct(private AttendeeController $controller)
    {
    }

    public function register(RestRouteRegistry $routes): void
    {
        $collection = '/events/(?P<event_id>\d+)/attendees';
        $member = $collection . '/(?P<attendee_id>\d+)';
        $routes->registerAuthenticatedPost(SystemRouteRegistrar::NAMESPACE, $collection, $this->controller->create(...));
        $routes->registerAuthenticatedPatch(SystemRouteRegistrar::NAMESPACE, $member, $this->controller->update(...));
        foreach (AttendeeCommand::cases() as $command) {
            $routes->registerAuthenticatedPost(
                SystemRouteRegistrar::NAMESPACE,
                $member . '/' . $command->value,
                fn (RestRequest $request): ApiResponse => $this->controller->transition($request, $command),
            );
        }
        $routes->registerAuthenticatedPost(SystemRouteRegistrar::NAMESPACE, $member . '/make-primary', $this->controller->makePrimary(...));
    }
}
