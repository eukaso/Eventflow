<?php

namespace EventFlow\Presentation\Api;

use EventFlow\Application\Invitation\InvitationOperations;

final readonly class InvitationAccessController
{
    public function __construct(
        private InvitationOperations $invitations,
        private AuthenticatedRequestContextFactory $contexts,
        private InvitationAccessRequestMapper $requests,
        private InvitationPresenter $presenter,
    ) {
    }

    public function list(RestRequest $request): ApiResponse
    {
        $context = $this->contexts->create($request, MutationPreconditionPolicy::NONE);
        [$limit, $after] = $this->requests->page($request);
        $page = $this->invitations->list(
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
        $record = $this->invitations->read(
            $context->principal,
            $this->requests->scope($request),
            $this->requests->invitationId($request),
        );
        return $this->presenter->resource($record, $context->requestId);
    }

    public function update(RestRequest $request): ApiResponse
    {
        $context = $this->contexts->create($request, MutationPreconditionPolicy::IF_MATCH_AND_IDEMPOTENCY_KEY);
        $scope = $this->requests->scope($request);
        $outcome = $this->invitations->update(
            $context->principal,
            $scope,
            $this->requests->invitationId($request),
            $this->requests->patch($request, $context->requiredExpectedVersion()),
            $context->requiredIdempotencyKey(),
        );
        return $this->presenter->outcome($outcome, $scope, $context->requestId);
    }

    public function transition(RestRequest $request, InvitationAccessCommand $command): ApiResponse
    {
        $context = $this->contexts->create($request, MutationPreconditionPolicy::IDEMPOTENCY_KEY);
        $this->requests->requireEmptyBody($request);
        $scope = $this->requests->scope($request);
        $invitationId = $this->requests->invitationId($request);
        $outcome = match ($command) {
            InvitationAccessCommand::ARCHIVE => $this->invitations->archive(
                $context->principal,
                $scope,
                $invitationId,
                $context->requiredIdempotencyKey(),
            ),
            InvitationAccessCommand::RESTORE => $this->invitations->restore(
                $context->principal,
                $scope,
                $invitationId,
                $context->requiredIdempotencyKey(),
            ),
        };
        return $this->presenter->outcome($outcome, $scope, $context->requestId);
    }
}
