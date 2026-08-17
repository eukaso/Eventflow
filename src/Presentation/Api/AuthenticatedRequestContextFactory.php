<?php

namespace EventFlow\Presentation\Api;

use EventFlow\Application\Error\{PreconditionDetails, PreconditionHeader, RequestIdFactory};

final readonly class AuthenticatedRequestContextFactory
{
    public function __construct(
        private AuthenticatedPrincipalResolver $principals,
        private RequestIdFactory $requestIds,
    ) {
    }

    public function create(RestRequest $request, MutationPreconditionPolicy $policy): AuthenticatedRequestContext
    {
        $requestId = $this->requestIds->fromUntrusted($request->header('X-Request-ID'));
        $principal = $this->principals->resolve($request);
        $idempotencyKey = $policy->requiresIdempotencyKey() ? $this->idempotencyKey($request) : null;
        $expectedVersion = $policy->requiresIfMatch() ? $this->expectedVersion($request) : null;
        return new AuthenticatedRequestContext($principal, $requestId, $idempotencyKey, $expectedVersion);
    }

    private function idempotencyKey(RestRequest $request): string
    {
        $key = $request->header('Idempotency-Key');
        if ($key === null) {
            throw new RequestInputException('precondition_required', new PreconditionDetails(PreconditionHeader::IDEMPOTENCY_KEY));
        }
        if (strlen($key) < 8 || strlen($key) > 255) {
            throw new RequestInputException('validation_failed');
        }
        return $key;
    }

    private function expectedVersion(RestRequest $request): int
    {
        $ifMatch = $request->header('If-Match');
        if ($ifMatch === null) {
            throw new RequestInputException('precondition_required', new PreconditionDetails(PreconditionHeader::IF_MATCH));
        }
        if (!preg_match('/^(?:([0-9]+)|"([0-9]+)"|W\/"([0-9]+)")$/', $ifMatch, $matches)) {
            throw new RequestInputException('validation_failed');
        }
        $candidate = $matches[1] !== '' ? $matches[1] : ($matches[2] !== '' ? $matches[2] : $matches[3]);
        $version = filter_var($candidate, FILTER_VALIDATE_INT, ['options' => ['min_range' => 0]]);
        if ($version === false) {
            throw new RequestInputException('validation_failed');
        }
        return $version;
    }
}
