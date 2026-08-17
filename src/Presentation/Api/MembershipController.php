<?php

namespace EventFlow\Presentation\Api;

use EventFlow\Application\Membership\MembershipCommands;

final readonly class MembershipController
{
    public function __construct(
        private MembershipCommands $memberships,
        private AuthenticatedRequestContextFactory $contexts,
        private MembershipRequestMapper $requests,
        private MembershipPresenter $presenter,
    ) {
    }

    public function grant(RestRequest $request): ApiResponse
    {
        $context = $this->contexts->create($request, MutationPreconditionPolicy::IDEMPOTENCY_KEY);
        $command = $this->requests->grant($request);
        $outcome = $this->memberships->grant($context->principal, $command, $context->requiredIdempotencyKey());
        return $this->presenter->outcome($outcome, $command->eventScope, $context->requestId);
    }

    public function change(RestRequest $request): ApiResponse
    {
        $context = $this->contexts->create($request, MutationPreconditionPolicy::IDEMPOTENCY_KEY);
        $command = $this->requests->change($request);
        $outcome = $this->memberships->change($context->principal, $command, $context->requiredIdempotencyKey());
        return $this->presenter->outcome($outcome, $command->eventScope, $context->requestId);
    }

    public function transition(RestRequest $request, MembershipCommand $command): ApiResponse
    {
        $context = $this->contexts->create($request, MutationPreconditionPolicy::IDEMPOTENCY_KEY);
        $this->requests->requireEmptyBody($request);
        $scope = $this->requests->scope($request);
        $membershipId = $this->requests->membershipId($request);
        $outcome = match ($command) {
            MembershipCommand::SUSPEND => $this->memberships->suspend($context->principal, $scope, $membershipId, $context->requiredIdempotencyKey()),
            MembershipCommand::REACTIVATE => $this->memberships->reactivate($context->principal, $scope, $membershipId, $context->requiredIdempotencyKey()),
            MembershipCommand::REVOKE => $this->memberships->revoke($context->principal, $scope, $membershipId, $context->requiredIdempotencyKey()),
        };
        return $this->presenter->outcome($outcome, $scope, $context->requestId);
    }

    public function makePrimaryOwner(RestRequest $request): ApiResponse
    {
        $context = $this->contexts->create($request, MutationPreconditionPolicy::IDEMPOTENCY_KEY);
        $command = $this->requests->transfer($request);
        $outcome = $this->memberships->transferPrimaryOwner($context->principal, $command, $context->requiredIdempotencyKey());
        return $this->presenter->outcome($outcome, $command->eventScope, $context->requestId);
    }
}
