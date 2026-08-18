<?php

namespace EventFlow\Presentation\Api;

use EventFlow\Application\Communication\TemplateCommands;

final readonly class TemplateController
{
    public function __construct(
        private TemplateCommands $templates,
        private AuthenticatedRequestContextFactory $contexts,
        private TemplateRequestMapper $requests,
        private TemplatePresenter $presenter,
    ) {}

    public function create(RestRequest $request): ApiResponse
    {
        $context = $this->contexts->create($request, MutationPreconditionPolicy::IDEMPOTENCY_KEY);
        $input = $this->requests->draft($request);
        $outcome = $this->templates->createDraft(
            $context->principal,
            $input->scope,
            $input->key,
            $input->name,
            $input->channel,
            $input->type,
            $input->subject,
            $input->body,
            $input->plainText,
            $input->allowedFields,
            $context->requiredIdempotencyKey(),
        );
        return $this->presenter->outcome($outcome, $input->scope->eventId, $context->requestId);
    }

    public function publish(RestRequest $request): ApiResponse
    {
        $context = $this->contexts->create($request, MutationPreconditionPolicy::IDEMPOTENCY_KEY);
        $input = $this->requests->publication($request);
        $outcome = $this->templates->publish(
            $context->principal,
            $input['scope'],
            $input['template_id'],
            $context->requiredIdempotencyKey(),
        );
        return $this->presenter->outcome($outcome, $input['scope']->eventId, $context->requestId);
    }
}
