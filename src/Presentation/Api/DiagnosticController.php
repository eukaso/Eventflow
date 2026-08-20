<?php

namespace EventFlow\Presentation\Api;

use EventFlow\Application\Observability\DiagnosticExport;

final readonly class DiagnosticController
{
    public function __construct(
        private DiagnosticExport $diagnostics,
        private AuthenticatedRequestContextFactory $contexts,
        private DiagnosticRequestMapper $requests,
        private DiagnosticPresenter $presenter,
    ) {
    }

    public function export(RestRequest $request): ApiResponse
    {
        $context = $this->contexts->create($request, MutationPreconditionPolicy::NONE);
        return $this->presenter->bundle($this->diagnostics->export(
            $context->principal,
            $this->requests->scope($request),
            $context->requestId,
        ), $context->requestId);
    }
}
