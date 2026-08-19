<?php

namespace EventFlow\Presentation\Api;

use EventFlow\Application\Event\EventDraftCommands;
use EventFlow\Application\Event\EventLifecycleCommands;
use EventFlow\Application\Event\EventQueries;

final readonly class EventController
{
    public function __construct(
        private EventLifecycleCommands $events,
        private EventQueries $queries,
        private EventDraftCommands $drafts,
        private AuthenticatedRequestContextFactory $contexts,
        private EventRequestMapper $requests,
        private EventPresenter $presenter,
    ) {
    }

    public function list(RestRequest $request): ApiResponse
    {
        $context = $this->contexts->create($request, MutationPreconditionPolicy::NONE);
        [$limit, $after] = $this->requests->page($request);
        return $this->presenter->page($this->queries->listAccessible($context->principal, $limit, $after), $context->requestId);
    }

    public function read(RestRequest $request): ApiResponse
    {
        $context = $this->contexts->create($request, MutationPreconditionPolicy::NONE);
        $event = $this->queries->read($context->principal, $this->requests->scope($request));
        return $this->presenter->resource($event, $context->requestId);
    }

    public function update(RestRequest $request): ApiResponse
    {
        $context = $this->contexts->create($request, MutationPreconditionPolicy::IF_MATCH_AND_IDEMPOTENCY_KEY);
        $scope = $this->requests->scope($request);
        $patch = $this->requests->patch($request, $context->requiredExpectedVersion());
        $outcome = $this->drafts->updateDraft($context->principal, $scope, $patch, $context->requiredIdempotencyKey());
        return $this->presenter->outcome($outcome, $context->requestId);
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
