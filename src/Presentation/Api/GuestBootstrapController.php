<?php

namespace EventFlow\Presentation\Api;

use EventFlow\Application\Error\RequestIdFactory;
use EventFlow\Application\GuestAccess\{GuestCredentialType, GuestSessionBootstrap};

final readonly class GuestBootstrapController
{
    public function __construct(
        private GuestSessionBootstrap $guestAccess,
        private PublicBootstrapRateLimiter $rateLimiter,
        private RequestIdFactory $requestIds,
        private GuestBootstrapRequestMapper $requests,
        private GuestSessionPresenter $presenter,
    ) {
    }

    public function bootstrap(RestRequest $request): ApiResponse
    {
        $requestId = $this->requestIds->fromUntrusted($request->header('X-Request-ID'));
        $credential = $this->requests->credential($request);
        $this->rateLimiter->consume($request->clientAddress(), hash('sha256', $credential));
        $credentials = $this->guestAccess->bootstrap($credential, GuestCredentialType::INVITATION);
        return $this->presenter->bootstrap($credentials, $requestId);
    }
}
