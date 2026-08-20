<?php

namespace EventFlow\Presentation\Api;

use EventFlow\Application\Export\ExportAccess;
use EventFlow\Application\Export\ExportArtifactReader;
use EventFlow\Application\Export\ExportDelivery;

final readonly class ExportController
{
    public function __construct(
        private ExportDelivery $exports,
        private ExportAccess $access,
        private ExportArtifactReader $artifacts,
        private AuthenticatedRequestContextFactory $contexts,
        private ExportRequestMapper $requests,
        private ExportPresenter $presenter,
    ) {}

    public function list(RestRequest $request): ApiResponse
    {
        $context = $this->contexts->create($request, MutationPreconditionPolicy::NONE);
        [$limit, $after, $status, $containsPii] = $this->requests->page($request);
        return $this->presenter->page($this->access->list($context->principal, $this->requests->scope($request), $limit, $after, $status, $containsPii), $context->requestId);
    }

    public function read(RestRequest $request): ApiResponse
    {
        $context = $this->contexts->create($request, MutationPreconditionPolicy::NONE);
        return $this->presenter->resource($this->access->read($context->principal, $this->requests->scope($request), $this->requests->exportId($request)), $context->requestId);
    }

    public function create(RestRequest $request): ApiResponse
    {
        $context = $this->contexts->create($request, MutationPreconditionPolicy::IDEMPOTENCY_KEY);
        [$type, $format, $purpose] = $this->requests->creation($request);
        $scope = $this->requests->scope($request);
        $outcome = $this->exports->request($context->principal, $scope, $type, $format, $purpose, $context->requiredIdempotencyKey());
        return $this->presenter->creation($outcome, $scope->eventId, $context->requestId);
    }

    public function download(RestRequest $request): ApiResponse
    {
        $context = $this->contexts->create($request, MutationPreconditionPolicy::NONE);
        $scope = $this->requests->scope($request);
        $id = $this->requests->exportId($request);
        $grant = $this->exports->authorizeDownload($context->principal, $scope, $id);
        $content = $this->artifacts->read($grant);
        $this->exports->recordDownload($context->principal, $scope, $id);
        return $this->presenter->download($grant, $content, $context->requestId);
    }
}
