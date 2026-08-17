<?php

namespace EventFlow\Presentation\Api;

use EventFlow\Application\Event\EventLifecycleCommands;

final readonly class EventController
{
    public function __construct(
        private EventLifecycleCommands $events,
        private AuthenticatedRequestContextFactory $contexts,
        private EventRequestMapper $requests,
        private EventPresenter $presenter,
    ) {
    }

    public function create(RestRequest $request): ApiResponse
    {
        $context = $this->contexts->create($request, MutationPreconditionPolicy::IDEMPOTENCY_KEY);
        $outcome = $this->events->create($context->principal, $this->requests->create($request), $context->requiredIdempotencyKey());
        return $this->presenter->outcome($outcome, $context->requestId);
    }

    public function transition(RestRequest $request, EventLifecycleCommand $command): ApiResponse
    {
        $context = $this->contexts->create($request, MutationPreconditionPolicy::IDEMPOTENCY_KEY);
        $scope = $this->requests->scope($request);
        $outcome = match ($command) {
            EventLifecycleCommand::ACTIVATE => $this->events->activate($context->principal, $scope, $context->requiredIdempotencyKey()),
            EventLifecycleCommand::COMPLETE => $this->events->complete($context->principal, $scope, $context->requiredIdempotencyKey()),
            EventLifecycleCommand::CANCEL => $this->events->cancel($context->principal, $scope, $context->requiredIdempotencyKey()),
            EventLifecycleCommand::ARCHIVE => $this->events->archive($context->principal, $scope, $context->requiredIdempotencyKey()),
            EventLifecycleCommand::RESTORE => $this->events->restore($context->principal, $scope, $context->requiredIdempotencyKey()),
        };
        return $this->presenter->outcome($outcome, $context->requestId);
    }
}
