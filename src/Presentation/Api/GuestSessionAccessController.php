<?php

namespace EventFlow\Presentation\Api;

use EventFlow\Application\GuestAccess\GuestSessionAccess;

final readonly class GuestSessionAccessController
{
    public function __construct(
        private GuestSessionAccess $sessions,
        private GuestRequestContextFactory $contexts,
        private GuestSessionAccessRequestMapper $requests,
        private GuestSessionAccessPresenter $presenter,
    ) {
    }

    public function context(RestRequest $request): ApiResponse
    {
        $context = $this->contexts->readOnly($request);
        return $this->presenter->context($this->sessions->context($context->principal), $context->requestId);
    }

    public function response(RestRequest $request): ApiResponse
    {
        $context = $this->contexts->readOnly($request);
        return $this->presenter->response($this->sessions->response($context->principal), $context->requestId);
    }

    public function logout(RestRequest $request): ApiResponse
    {
        $context = $this->contexts->csrfProtected($request);
        $this->requests->requireEmptyBody($request);
        $this->sessions->logout($context->principal);
        return $this->presenter->logout($context->requestId);
    }
}
