<?php

namespace EventFlow\Presentation\Api;

use EventFlow\Application\Error\{PreconditionDetails, PreconditionHeader, RequestIdFactory};
use EventFlow\Application\GuestAccess\GuestSessionAuthenticator;

final readonly class GuestRequestContextFactory
{
    public function __construct(
        private GuestSessionAuthenticator $sessions,
        private RequestIdFactory $requestIds,
    ) {
    }

    public function stateChanging(RestRequest $request): GuestRequestContext
    {
        $requestId = $this->requestIds->fromUntrusted($request->header('X-Request-ID'));
        $sessionToken = $request->cookie(GuestSessionCookie::NAME);
        if ($sessionToken === null || !preg_match('/^[a-f0-9]{64}$/', $sessionToken)) {
            throw new RequestInputException('guest_session_invalid');
        }
        $principal = $this->sessions->authenticate(
            $sessionToken,
            $request->header('X-EventFlow-CSRF'),
            true,
            $request->sameOrigin(),
        );
        return new GuestRequestContext(
            $principal,
            $requestId,
            $this->idempotencyKey($request),
            $this->expectedRevision($request),
        );
    }

    private function idempotencyKey(RestRequest $request): string
    {
        $key = $request->header('Idempotency-Key');
        if ($key === null) throw new RequestInputException('precondition_required', new PreconditionDetails(PreconditionHeader::IDEMPOTENCY_KEY));
        if (strlen($key) < 8 || strlen($key) > 255) throw new RequestInputException('validation_failed');
        return $key;
    }

    private function expectedRevision(RestRequest $request): int
    {
        $ifMatch = $request->header('If-Match');
        if ($ifMatch === null) throw new RequestInputException('precondition_required', new PreconditionDetails(PreconditionHeader::IF_MATCH));
        if (!preg_match('/^(?:([0-9]+)|"([0-9]+)"|W\/"([0-9]+)")$/', $ifMatch, $matches)) {
            throw new RequestInputException('validation_failed');
        }
        $candidate = $matches[1] !== '' ? $matches[1] : ($matches[2] !== '' ? $matches[2] : $matches[3]);
        $revision = filter_var($candidate, FILTER_VALIDATE_INT, ['options' => ['min_range' => 0]]);
        if ($revision === false) throw new RequestInputException('validation_failed');
        return $revision;
    }
}
