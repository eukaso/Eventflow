<?php

namespace EventFlow\Presentation\Api;

use EventFlow\Application\Attendee\AttendeeQueries;

final readonly class AttendeeQueryController
{
    public function __construct(
        private AttendeeQueries $attendees,
        private AuthenticatedRequestContextFactory $contexts,
        private AttendeeQueryRequestMapper $requests,
        private AttendeePresenter $presenter,
    ) {
    }

    public function list(RestRequest $request): ApiResponse
    {
        $context = $this->contexts->create($request, MutationPreconditionPolicy::NONE);
        [$limit, $after] = $this->requests->page($request);
        $page = $this->attendees->list(
            $context->principal,
            $this->requests->scope($request),
            $limit,
            $after,
        );
        return $this->presenter->page($page, $context->requestId);
    }

    public function read(RestRequest $request): ApiResponse
    {
        $context = $this->contexts->create($request, MutationPreconditionPolicy::NONE);
        $record = $this->attendees->read(
            $context->principal,
            $this->requests->scope($request),
            $this->requests->attendeeId($request),
        );
        return $this->presenter->resource($record, $context->requestId);
    }
}
