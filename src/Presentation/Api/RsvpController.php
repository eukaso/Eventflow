<?php

namespace EventFlow\Presentation\Api;

use EventFlow\Application\Attendee\RsvpCommands;

final readonly class RsvpController
{
    public function __construct(
        private RsvpCommands $rsvps,
        private GuestRequestContextFactory $contexts,
        private RsvpRequestMapper $requests,
        private RsvpPresenter $presenter,
    ) {
    }

    public function submit(RestRequest $request): ApiResponse
    {
        $context = $this->contexts->stateChanging($request);
        $command = $this->requests->submit($request, $context->principal, $context->expectedRevision);
        $outcome = $this->rsvps->submitRsvp($context->principal, $command, $context->idempotencyKey);
        return $this->presenter->outcome($outcome, $context->requestId);
    }
}
