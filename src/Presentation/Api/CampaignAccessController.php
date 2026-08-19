<?php

namespace EventFlow\Presentation\Api;

use EventFlow\Application\Communication\CampaignAccess;

final readonly class CampaignAccessController
{
    public function __construct(
        private CampaignAccess $campaigns,
        private AuthenticatedRequestContextFactory $contexts,
        private CampaignAccessRequestMapper $requests,
        private CampaignAccessPresenter $presenter,
    ) {}

    public function list(RestRequest $request): ApiResponse
    {
        $context = $this->contexts->create($request, MutationPreconditionPolicy::NONE);
        [$limit, $after] = $this->requests->page($request);
        return $this->presenter->page(
            $this->campaigns->list($context->principal, $this->requests->scope($request), $limit, $after),
            $context->requestId,
        );
    }

    public function read(RestRequest $request): ApiResponse
    {
        $context = $this->contexts->create($request, MutationPreconditionPolicy::NONE);
        return $this->presenter->resource(
            $this->campaigns->read($context->principal, $this->requests->scope($request), $this->requests->campaignId($request)),
            $context->requestId,
        );
    }

    public function update(RestRequest $request): ApiResponse
    {
        $context = $this->contexts->create($request, MutationPreconditionPolicy::IF_MATCH_AND_IDEMPOTENCY_KEY);
        $scope = $this->requests->scope($request);
        $id = $this->requests->campaignId($request);
        $current = $this->campaigns->read($context->principal, $scope, $id);
        $outcome = $this->campaigns->update(
            $context->principal,
            $scope,
            $id,
            $this->requests->replacement($request, $current, $context->requiredExpectedVersion()),
            $context->requiredIdempotencyKey(),
        );
        return $this->presenter->outcome($outcome, $scope->eventId, $context->requestId);
    }

    public function audiencePreview(RestRequest $request): ApiResponse
    {
        $context = $this->contexts->create($request, MutationPreconditionPolicy::NONE);
        $this->requests->requireEmptyBody($request);
        return $this->presenter->preview(
            $this->campaigns->audiencePreview($context->principal, $this->requests->scope($request), $this->requests->campaignId($request)),
            $context->requestId,
        );
    }

    public function schedule(RestRequest $request): ApiResponse
    {
        $context = $this->contexts->create($request, MutationPreconditionPolicy::IF_MATCH_AND_IDEMPOTENCY_KEY);
        $scope = $this->requests->scope($request);
        $outcome = $this->campaigns->schedule(
            $context->principal,
            $scope,
            $this->requests->campaignId($request),
            $context->requiredExpectedVersion(),
            $this->requests->scheduledAt($request),
            $context->requiredIdempotencyKey(),
        );
        return $this->presenter->outcome($outcome, $scope->eventId, $context->requestId);
    }

    public function cancel(RestRequest $request): ApiResponse
    {
        $context = $this->contexts->create($request, MutationPreconditionPolicy::IF_MATCH_AND_IDEMPOTENCY_KEY);
        $this->requests->requireEmptyBody($request);
        $scope = $this->requests->scope($request);
        $outcome = $this->campaigns->cancel(
            $context->principal,
            $scope,
            $this->requests->campaignId($request),
            $context->requiredExpectedVersion(),
            $context->requiredIdempotencyKey(),
        );
        return $this->presenter->outcome($outcome, $scope->eventId, $context->requestId);
    }
}
