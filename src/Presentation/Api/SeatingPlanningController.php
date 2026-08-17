<?php

namespace EventFlow\Presentation\Api;

use EventFlow\Application\Seating\SeatingPlanningCommands;

final readonly class SeatingPlanningController
{
    public function __construct(
        private SeatingPlanningCommands $seating,
        private AuthenticatedRequestContextFactory $contexts,
        private SeatingPlanningRequestMapper $requests,
        private SeatingPlanningPresenter $presenter,
    ) {
    }

    public function recommend(RestRequest $request): ApiResponse
    {
        $context = $this->contexts->create($request, MutationPreconditionPolicy::NONE);
        $input = $this->requests->recommendation($request);
        return $this->presenter->recommendation(
            $this->seating->recommend($context->principal, $input->scope, $input->seed),
            $context->requestId,
        );
    }

    public function move(RestRequest $request): ApiResponse
    {
        $context = $this->contexts->create($request, MutationPreconditionPolicy::IDEMPOTENCY_KEY);
        $input = $this->requests->assignment($request);
        $outcome = $this->seating->assign(
            $context->principal,
            $input->scope,
            $input->attendeeId,
            $input->tableId,
            $input->seatId,
            $input->expectedAssignmentId,
            $input->overrideRequiredGroup,
            $input->overrideReason,
            $context->requiredIdempotencyKey(),
        );
        return $this->presenter->assignment($outcome, $input->scope->eventId, $input->attendeeId, $context->requestId);
    }
}
