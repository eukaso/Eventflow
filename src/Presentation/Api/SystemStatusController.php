<?php

namespace EventFlow\Presentation\Api;

use EventFlow\Application\Error\RequestIdFactory;
use EventFlow\Application\Health\SystemHealthService;
use EventFlow\Application\Observability\ObservabilityService;
use Throwable;

final readonly class SystemStatusController
{
    public function __construct(
        private SystemHealthService $health,
        private SystemStatusPresenter $presenter,
        private RequestIdFactory $requestIds,
        private ApiErrorTranslator $errors,
        private ObservabilityService $observability,
    ) {
    }

    public function health(RestRequest $request): ApiResponse
    {
        $requestId = $this->requestIds->fromUntrusted($request->header('X-Request-ID'));
        try {
            $response = $this->presenter->health($this->health->health(), $requestId);
            $this->observability->requestCompleted('api', true);
            return $response;
        } catch (Throwable $failure) {
            return $this->errors->translate($failure, $requestId);
        }
    }

    public function readiness(RestRequest $request): ApiResponse
    {
        $requestId = $this->requestIds->fromUntrusted($request->header('X-Request-ID'));
        try {
            $response = $this->presenter->readiness($this->health->readiness(), $requestId);
            $this->observability->requestCompleted('api', true);
            return $response;
        } catch (Throwable $failure) {
            return $this->errors->translate($failure, $requestId);
        }
    }
}
