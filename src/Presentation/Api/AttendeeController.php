<?php

namespace EventFlow\Presentation\Api;

use EventFlow\Application\Attendee\AttendeeCommands;

final readonly class AttendeeController
{
    public function __construct(
        private AttendeeCommands $attendees,
        private AuthenticatedRequestContextFactory $contexts,
        private AttendeeRequestMapper $requests,
        private AttendeePresenter $presenter,
    ) {
    }

    public function create(RestRequest $request): ApiResponse
    {
        $context = $this->contexts->create($request, MutationPreconditionPolicy::IDEMPOTENCY_KEY);
        $mutation = $this->requests->create($request);
        $outcome = $this->attendees->createAttendee($context->principal, $mutation->scope, $mutation->invitationId, $mutation->attendee, $context->requiredIdempotencyKey());
        return $this->presenter->outcome($outcome, $mutation->scope->eventId, $context->requestId);
    }

    public function update(RestRequest $request): ApiResponse
    {
        $context = $this->contexts->create($request, MutationPreconditionPolicy::IDEMPOTENCY_KEY);
        $mutation = $this->requests->update($request);
        $outcome = $this->attendees->updateAttendee(
            $context->principal,
            $mutation->scope,
            $mutation->invitationId,
            $mutation->attendee->attendeeId ?? throw new \LogicException('mapped_attendee_id_missing'),
            $mutation->attendee,
            $context->requiredIdempotencyKey(),
        );
        return $this->presenter->outcome($outcome, $mutation->scope->eventId, $context->requestId);
    }

    public function transition(RestRequest $request, AttendeeCommand $command): ApiResponse
    {
        $context = $this->contexts->create($request, MutationPreconditionPolicy::IDEMPOTENCY_KEY);
        $scope = $this->requests->scope($request);
        $attendeeId = $this->requests->attendeeId($request);
        $invitationId = $this->requests->invitationId($request);
        $outcome = match ($command) {
            AttendeeCommand::CANCEL => $this->attendees->cancel($context->principal, $scope, $invitationId, $attendeeId, $context->requiredIdempotencyKey()),
            AttendeeCommand::RESTORE => $this->attendees->restore($context->principal, $scope, $invitationId, $attendeeId, $context->requiredIdempotencyKey()),
        };
        return $this->presenter->outcome($outcome, $scope->eventId, $context->requestId);
    }

    public function makePrimary(RestRequest $request): ApiResponse
    {
        $context = $this->contexts->create($request, MutationPreconditionPolicy::IDEMPOTENCY_KEY);
        $scope = $this->requests->scope($request);
        $targetId = $this->requests->attendeeId($request);
        $invitationId = $this->requests->invitationId($request, true);
        $expectedPrimaryId = $this->requests->expectedPrimaryId($request);
        $outcome = $this->attendees->transferPrimary($context->principal, $scope, $invitationId, $expectedPrimaryId, $targetId, $context->requiredIdempotencyKey());
        return $this->presenter->outcome($outcome, $scope->eventId, $context->requestId);
    }
}
