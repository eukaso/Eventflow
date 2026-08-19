<?php

namespace EventFlow\Presentation\Api;

use EventFlow\Application\Authorization\PrincipalContext;
use EventFlow\Application\Seating\{SeatingResourceAccess, SeatingSeat};

final readonly class SeatingResourceController
{
    public function __construct(
        private SeatingResourceAccess $seating,
        private AuthenticatedRequestContextFactory $contexts,
        private SeatingResourceRequestMapper $requests,
        private SeatingResourcePresenter $presenter,
    ) {
    }

    public function listTables(RestRequest $request): ApiResponse
    {
        $context = $this->contexts->create($request, MutationPreconditionPolicy::NONE);
        return $this->presenter->tables($this->seating->snapshot($context->principal, $this->requests->scope($request)), $context->requestId);
    }

    public function table(RestRequest $request): ApiResponse
    {
        $context = $this->contexts->create($request, MutationPreconditionPolicy::NONE);
        return $this->presenter->tableDetail($this->seating->table($context->principal, $this->requests->scope($request), $this->requests->tableId($request)), $context->requestId);
    }

    public function updateTable(RestRequest $request): ApiResponse
    {
        $context = $this->contexts->create($request, MutationPreconditionPolicy::IF_MATCH_AND_IDEMPOTENCY_KEY);
        $scope = $this->requests->scope($request); $id = $this->requests->tableId($request);
        $current = $this->seating->table($context->principal, $scope, $id);
        $outcome = $this->seating->updateTable($context->principal, $scope, $id, $this->requests->tableReplacement($request, $current, $context->requiredExpectedVersion()), $context->requiredIdempotencyKey());
        return $this->presenter->outcome($outcome, $context->requestId);
    }

    public function listSeats(RestRequest $request): ApiResponse
    {
        $context = $this->contexts->create($request, MutationPreconditionPolicy::NONE);
        $table = $this->seating->table($context->principal, $this->requests->scope($request), $this->requests->tableId($request));
        return $this->presenter->seats($table->seats, $context->requestId);
    }

    public function createSeat(RestRequest $request): ApiResponse
    {
        $context = $this->contexts->create($request, MutationPreconditionPolicy::IDEMPOTENCY_KEY);
        $input = $this->requests->seatCreate($request);
        $outcome = $this->seating->createSeat($context->principal, $input->scope, $input->tableId, $input->label, $input->accessible, $input->sortOrder, $context->requiredIdempotencyKey());
        $location = '/wp-json/eventflow/v1/events/' . $input->scope->eventId . '/tables/' . $input->tableId . '/seats/' . $outcome->reference->entityId;
        return $this->presenter->outcome($outcome, $context->requestId, $location);
    }

    public function seat(RestRequest $request): ApiResponse
    {
        $context = $this->contexts->create($request, MutationPreconditionPolicy::NONE);
        $seat = $this->scopedSeat($request, $context->principal);
        return $this->presenter->seatDetail($seat, $context->requestId);
    }

    public function updateSeat(RestRequest $request): ApiResponse
    {
        $context = $this->contexts->create($request, MutationPreconditionPolicy::IF_MATCH_AND_IDEMPOTENCY_KEY);
        $scope = $this->requests->scope($request); $seat = $this->scopedSeat($request, $context->principal);
        $outcome = $this->seating->updateSeat($context->principal, $scope, $seat->seatId, $this->requests->seatReplacement($request, $seat, $context->requiredExpectedVersion()), $context->requiredIdempotencyKey());
        return $this->presenter->outcome($outcome, $context->requestId);
    }

    public function listGroups(RestRequest $request): ApiResponse
    {
        $context = $this->contexts->create($request, MutationPreconditionPolicy::NONE);
        return $this->presenter->groups($this->seating->snapshot($context->principal, $this->requests->scope($request)), $context->requestId);
    }

    public function group(RestRequest $request): ApiResponse
    {
        $context = $this->contexts->create($request, MutationPreconditionPolicy::NONE);
        return $this->presenter->groupDetail($this->seating->group($context->principal, $this->requests->scope($request), $this->requests->groupId($request)), $context->requestId);
    }

    public function updateGroup(RestRequest $request): ApiResponse
    {
        $context = $this->contexts->create($request, MutationPreconditionPolicy::IF_MATCH_AND_IDEMPOTENCY_KEY);
        $scope = $this->requests->scope($request); $id = $this->requests->groupId($request);
        $current = $this->seating->group($context->principal, $scope, $id);
        $outcome = $this->seating->updateGroup($context->principal, $scope, $id, $this->requests->groupReplacement($request, $current, $context->requiredExpectedVersion()), $context->requiredIdempotencyKey());
        return $this->presenter->outcome($outcome, $context->requestId);
    }

    private function scopedSeat(RestRequest $request, PrincipalContext $principal): SeatingSeat
    {
        $seat = $this->seating->seat($principal, $this->requests->scope($request), $this->requests->seatId($request));
        if ($seat->tableId !== $this->requests->tableId($request)) throw new RequestInputException('resource_not_found');
        return $seat;
    }
}
