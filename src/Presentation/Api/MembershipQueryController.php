<?php

namespace EventFlow\Presentation\Api;

use EventFlow\Application\Membership\MembershipQueries;

final readonly class MembershipQueryController
{
    public function __construct(
        private MembershipQueries $memberships,
        private AuthenticatedRequestContextFactory $contexts,
        private MembershipQueryRequestMapper $requests,
        private MembershipPresenter $presenter,
    ) {
    }

    public function list(RestRequest $request): ApiResponse
    {
        $context = $this->contexts->create($request, MutationPreconditionPolicy::NONE);
        [$limit, $after] = $this->requests->page($request);
        $page = $this->memberships->list(
            $context->principal,
            $this->requests->scope($request),
            $limit,
            $after,
        );
        return $this->presenter->page($page, $context->requestId);
    }
}
