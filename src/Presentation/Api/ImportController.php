<?php

namespace EventFlow\Presentation\Api;

use EventFlow\Application\Import\ImportValidation;

final readonly class ImportController
{
    public function __construct(
        private ImportValidation $imports,
        private AuthenticatedRequestContextFactory $contexts,
        private ImportRequestMapper $requests,
        private ImportPresenter $presenter,
    ) {}

    public function validate(RestRequest $request): ApiResponse
    {
        $context = $this->contexts->create($request, MutationPreconditionPolicy::IDEMPOTENCY_KEY);
        $input = $this->requests->validation($request);
        $outcome = $this->imports->validate(
            $context->principal,
            $input->scope,
            $input->jobId,
            $input->mapping,
            $context->requiredIdempotencyKey(),
        );
        return $this->presenter->validation($outcome, $input->scope->eventId, $input->jobId, $context->requestId);
    }
}
