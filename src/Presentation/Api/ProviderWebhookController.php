<?php

namespace EventFlow\Presentation\Api;

use EventFlow\Application\Error\RequestIdFactory;
use EventFlow\Application\Provider\ProviderWebhookIngress;

final readonly class ProviderWebhookController
{
    public function __construct(
        private ProviderWebhookIngress $webhooks,
        private RequestIdFactory $requestIds,
        private ProviderWebhookRequestMapper $requests,
        private ProviderWebhookPresenter $presenter,
    ) {}

    public function ingest(RestRequest $request): ApiResponse
    {
        $requestId = $this->requestIds->fromUntrusted($request->header('X-Request-ID'));
        $input = $this->requests->ingress($request);
        $jobId = $this->webhooks->ingest($input['provider'], $input['headers'], $input['raw_body']);
        return $this->presenter->accepted($jobId, $requestId);
    }
}
