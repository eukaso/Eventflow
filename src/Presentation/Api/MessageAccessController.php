<?php

namespace EventFlow\Presentation\Api;

use EventFlow\Application\Communication\MessageAccess;

final readonly class MessageAccessController
{
    public function __construct(
        private MessageAccess $messages,
        private AuthenticatedRequestContextFactory $contexts,
        private MessageAccessRequestMapper $requests,
        private MessageAccessPresenter $presenter,
    ) {}

    public function list(RestRequest $request): ApiResponse
    {
        $context=$this->contexts->create($request,MutationPreconditionPolicy::NONE);
        [$limit,$after,$campaignId,$status]=$this->requests->page($request);
        return $this->presenter->page(
            $this->messages->list($context->principal,$this->requests->scope($request),$limit,$after,$campaignId,$status),
            $context->requestId,
        );
    }

    public function read(RestRequest $request): ApiResponse
    {
        $context=$this->contexts->create($request,MutationPreconditionPolicy::NONE);
        return $this->presenter->resource(
            $this->messages->read($context->principal,$this->requests->scope($request),$this->requests->messageId($request)),
            $context->requestId,
        );
    }

    public function retry(RestRequest $request): ApiResponse
    {
        $context=$this->contexts->create($request,MutationPreconditionPolicy::IF_MATCH_AND_IDEMPOTENCY_KEY);
        $this->requests->requireEmptyBody($request);
        $scope=$this->requests->scope($request);
        return $this->presenter->outcome(
            $this->messages->retry($context->principal,$scope,$this->requests->messageId($request),$context->requiredExpectedVersion(),$context->requiredIdempotencyKey()),
            $scope->eventId,
            $context->requestId,
        );
    }
}
