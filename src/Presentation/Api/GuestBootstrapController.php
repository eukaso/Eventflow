<?php

namespace EventFlow\Presentation\Api;

use EventFlow\Application\Error\RequestIdFactory;
use EventFlow\Application\GuestAccess\{GuestAccessException, GuestCredentialType, GuestSessionBootstrap};

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
        $credentialType = $this->requests->credentialType($request);
        $this->rateLimiter->consume($request->clientAddress(), hash('sha256', $credential));
        if ($credentialType !== null) {
            $credentials = $this->guestAccess->bootstrap($credential, $credentialType);
            return $this->presenter->bootstrap($credentials, $requestId);
        }
        try {
            $credentials = $this->guestAccess->bootstrap($credential, GuestCredentialType::INVITATION);
        } catch (GuestAccessException $failure) {
            if ($failure->safeCode !== 'guest_credential_invalid') {
                throw $failure;
            }
            $credentials = $this->guestAccess->bootstrap($credential, GuestCredentialType::MESSAGE_LINK);
        }
        return $this->presenter->bootstrap($credentials, $requestId);
    }
}
