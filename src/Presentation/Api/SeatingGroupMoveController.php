<?php

namespace EventFlow\Presentation\Api;

use EventFlow\Application\Seating\SeatingGroupMoves;

final readonly class SeatingGroupMoveController
{
    public function __construct(
        private SeatingGroupMoves $seating,
        private AuthenticatedRequestContextFactory $contexts,
        private SeatingGroupMoveRequestMapper $requests,
        private SeatingGroupMovePresenter $presenter,
    ) {}

    public function move(RestRequest $request): ApiResponse
    {
        $context = $this->contexts->create($request, MutationPreconditionPolicy::IF_MATCH_AND_IDEMPOTENCY_KEY);
        $input = $this->requests->move($request, $context->requiredExpectedVersion());
        $outcome = $this->seating->moveGroup(
            $context->principal,
            $input->scope,
            $input->groupId,
            $input->tableId,
            $input->expectedGroupRevision,
            $input->members,
            $input->overrideRequiredGroups,
            $input->overrideReason,
            $context->requiredIdempotencyKey(),
        );
        return $this->presenter->outcome($outcome, $input->scope->eventId, $input->groupId, $context->requestId);
    }
}
