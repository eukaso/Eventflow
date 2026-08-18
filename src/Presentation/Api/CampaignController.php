<?php

namespace EventFlow\Presentation\Api;

use EventFlow\Application\Communication\CampaignCommands;

final readonly class CampaignController
{
    public function __construct(
        private CampaignCommands $campaigns,
        private AuthenticatedRequestContextFactory $contexts,
        private CampaignRequestMapper $requests,
        private CampaignPresenter $presenter,
    ) {}

    public function create(RestRequest $request): ApiResponse
    {
        $context = $this->contexts->create($request, MutationPreconditionPolicy::IDEMPOTENCY_KEY);
        $input = $this->requests->creation($request);
        $outcome = $this->campaigns->createCampaign(
            $context->principal,
            $input->scope,
            $input->templateId,
            $input->name,
            $input->channel,
            $input->purpose,
            $input->audienceMode,
            $input->audience,
            $context->requiredIdempotencyKey(),
        );
        return $this->presenter->creation($outcome, $input->scope->eventId, $context->requestId);
    }

    public function queue(RestRequest $request): ApiResponse
    {
        $context = $this->contexts->create($request, MutationPreconditionPolicy::IDEMPOTENCY_KEY);
        $input = $this->requests->queue($request);
        $outcome = $this->campaigns->queue(
            $context->principal,
            $input['scope'],
            $input['campaign_id'],
            $context->requiredIdempotencyKey(),
        );
        return $this->presenter->queue($outcome, $input['scope']->eventId, $context->requestId);
    }
}
