<?php

namespace EventFlow\Presentation\Api;

use EventFlow\Application\Seating\SeatingPreparation;

final readonly class SeatingPreparationController
{
    public function __construct(
        private SeatingPreparation $seating,
        private AuthenticatedRequestContextFactory $contexts,
        private SeatingPreparationRequestMapper $requests,
        private SeatingPreparationPresenter $presenter,
    ) {
    }

    public function createTable(RestRequest $request): ApiResponse
    {
        $context = $this->contexts->create($request, MutationPreconditionPolicy::IDEMPOTENCY_KEY);
        $input = $this->requests->table($request);
        $outcome = $this->seating->createTable($context->principal, $input->scope, $input->name, $input->capacity, $input->seats, $context->requiredIdempotencyKey());
        return $this->presenter->outcome($outcome, $input->scope->eventId, 'tables', $context->requestId);
    }

    public function createGroup(RestRequest $request): ApiResponse
    {
        $context = $this->contexts->create($request, MutationPreconditionPolicy::IDEMPOTENCY_KEY);
        $input = $this->requests->group($request);
        $outcome = $this->seating->createGroup(
            $context->principal,
            $input->scope,
            $input->name,
            $input->category,
            $input->constraint,
            $input->priority,
            $input->attendeeIds,
            $context->requiredIdempotencyKey(),
        );
        return $this->presenter->outcome($outcome, $input->scope->eventId, 'seating-groups', $context->requestId);
    }

    public function readiness(RestRequest $request): ApiResponse
    {
        $context = $this->contexts->create($request, MutationPreconditionPolicy::NONE);
        $scope = $this->requests->scope($request);
        return $this->presenter->readiness($this->seating->readiness($context->principal, $scope), $context->requestId);
    }
}
