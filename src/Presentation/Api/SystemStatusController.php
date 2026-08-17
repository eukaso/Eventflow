<?php

namespace EventFlow\Presentation\Api;

use EventFlow\Application\Error\RequestIdFactory;
use EventFlow\Application\Health\SystemHealthService;
use Throwable;

final readonly class SystemStatusController
{
    public function __construct(
        private SystemHealthService $health,
        private SystemStatusPresenter $presenter,
        private RequestIdFactory $requestIds,
        private ApiErrorTranslator $errors,
    ) {
    }

    public function health(RestRequest $request): ApiResponse
    {
        $requestId = $this->requestIds->fromUntrusted($request->header('X-Request-ID'));
        try {
            return $this->presenter->health($this->health->health(), $requestId);
        } catch (Throwable $failure) {
            return $this->errors->translate($failure, $requestId);
        }
    }

    public function readiness(RestRequest $request): ApiResponse
    {
        $requestId = $this->requestIds->fromUntrusted($request->header('X-Request-ID'));
        try {
            return $this->presenter->readiness($this->health->readiness(), $requestId);
        } catch (Throwable $failure) {
            return $this->errors->translate($failure, $requestId);
        }
    }
}
