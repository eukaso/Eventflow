<?php

namespace EventFlow\Presentation\Api;

use EventFlow\Application\Audit\AuditAccess;

final readonly class AuditController
{
    public function __construct(
        private AuditAccess $audit,
        private AuthenticatedRequestContextFactory $contexts,
        private AuditRequestMapper $requests,
        private AuditPresenter $presenter,
    ) {
    }

    public function list(RestRequest $request): ApiResponse
    {
        $context = $this->contexts->create($request, MutationPreconditionPolicy::NONE);
        [$limit,$after,$action,$entityType,$entityId,$actorUserId,$source,$from,$until] = $this->requests->page($request);
        return $this->presenter->page($this->audit->list(
            $context->principal, $this->requests->scope($request), $limit, $after, $action,
            $entityType, $entityId, $actorUserId, $source, $from, $until,
        ), $context->requestId);
    }

    public function read(RestRequest $request): ApiResponse
    {
        $context = $this->contexts->create($request, MutationPreconditionPolicy::NONE);
        $this->requests->requireNoQuery($request);
        return $this->presenter->resource($this->audit->read(
            $context->principal, $this->requests->scope($request), $this->requests->auditLogId($request),
        ), $context->requestId);
    }

    public function integrity(RestRequest $request): ApiResponse
    {
        $context = $this->contexts->create($request, MutationPreconditionPolicy::NONE);
        $this->requests->requireNoQuery($request);
        return $this->presenter->integrity($this->audit->verifyIntegrity(
            $context->principal, $this->requests->scope($request),
        ), $context->requestId);
    }
}
