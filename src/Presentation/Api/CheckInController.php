<?php

namespace EventFlow\Presentation\Api;

use EventFlow\Application\CheckIn\{CheckInCommands, ReceptionSearch};

final readonly class CheckInController
{
    public function __construct(
        private ReceptionSearch $reception,
        private CheckInCommands $commands,
        private AuthenticatedRequestContextFactory $contexts,
        private CheckInRequestMapper $requests,
        private CheckInPresenter $presenter,
    ) {}

    public function search(RestRequest $request): ApiResponse
    {
        $context = $this->contexts->create($request, MutationPreconditionPolicy::NONE);
        $input = $this->requests->search($request);
        return $this->presenter->search(
            $this->reception->search($context->principal, $input->scope, $input->query, $input->limit),
            $context->requestId,
        );
    }

    public function lookup(RestRequest $request): ApiResponse
    {
        $context = $this->contexts->create($request, MutationPreconditionPolicy::NONE);
        $input = $this->requests->lookup($request);
        return $this->presenter->lookup(
            $this->reception->lookup($context->principal, $input->scope, $input->code),
            $context->requestId,
        );
    }

    public function checkIn(RestRequest $request): ApiResponse
    {
        $context = $this->contexts->create($request, MutationPreconditionPolicy::IDEMPOTENCY_KEY);
        $input = $this->requests->individual($request);
        $outcome = $this->commands->checkIn(
            $context->principal, $input->scope, $input->attendeeIds[0], $input->stationId,
            $input->method, $context->requiredIdempotencyKey(), $input->notes,
        );
        return $this->presenter->checkIn($outcome, $input->scope->eventId, $context->requestId);
    }

    public function bulk(RestRequest $request): ApiResponse
    {
        $context = $this->contexts->create($request, MutationPreconditionPolicy::IDEMPOTENCY_KEY);
        $input = $this->requests->bulk($request);
        $outcome = $this->commands->bulk(
            $context->principal, $input->scope, $input->attendeeIds, $input->stationId,
            $input->method, $context->requiredIdempotencyKey(), $input->notes,
        );
        return $this->presenter->bulk($outcome, $context->requestId);
    }

    public function reverse(RestRequest $request): ApiResponse
    {
        $context = $this->contexts->create($request, MutationPreconditionPolicy::IDEMPOTENCY_KEY);
        $input = $this->requests->reversal($request);
        $outcome = $this->commands->reverse(
            $context->principal, $input->scope, $input->checkInId, $input->reason,
            $context->requiredIdempotencyKey(),
        );
        return $this->presenter->reversal($outcome, $input->scope->eventId, $context->requestId);
    }
}
