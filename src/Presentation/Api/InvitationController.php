<?php

namespace EventFlow\Presentation\Api;

use EventFlow\Application\Invitation\InvitationCommands;

final readonly class InvitationController
{
    public function __construct(
        private InvitationCommands $invitations,
        private AuthenticatedRequestContextFactory $contexts,
        private InvitationRequestMapper $requests,
        private InvitationPresenter $presenter,
    ) {
    }

    public function create(RestRequest $request): ApiResponse
    {
        $context = $this->contexts->create($request, MutationPreconditionPolicy::IDEMPOTENCY_KEY);
        $command = $this->requests->create($request);
        $outcome = $this->invitations->create($context->principal, $command, $context->requiredIdempotencyKey());
        return $this->presenter->outcome($outcome, $command->eventScope, $context->requestId);
    }

    public function replaceCredential(RestRequest $request, InvitationCredentialCommand $command): ApiResponse
    {
        $context = $this->contexts->create($request, MutationPreconditionPolicy::IDEMPOTENCY_KEY);
        $scope = $this->requests->scope($request);
        $invitationId = $this->requests->invitationId($request);
        $expiresAt = $this->requests->replacementExpiry($request);
        $outcome = match ($command) {
            InvitationCredentialCommand::ACTIVATE => $this->invitations->reactivate($context->principal, $scope, $invitationId, $expiresAt, $context->requiredIdempotencyKey()),
            InvitationCredentialCommand::ROTATE_TOKEN => $this->invitations->rotateCredential($context->principal, $scope, $invitationId, $expiresAt, $context->requiredIdempotencyKey()),
        };
        return $this->presenter->outcome($outcome, $scope, $context->requestId);
    }

    public function revoke(RestRequest $request): ApiResponse
    {
        $context = $this->contexts->create($request, MutationPreconditionPolicy::IDEMPOTENCY_KEY);
        $this->requests->requireEmptyBody($request);
        $scope = $this->requests->scope($request);
        $invitationId = $this->requests->invitationId($request);
        $outcome = $this->invitations->revoke($context->principal, $scope, $invitationId, $context->requiredIdempotencyKey());
        return $this->presenter->outcome($outcome, $scope, $context->requestId);
    }
}
