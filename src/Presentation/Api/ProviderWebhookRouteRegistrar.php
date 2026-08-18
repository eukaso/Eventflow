<?php

namespace EventFlow\Presentation\Api;

final readonly class ProviderWebhookRouteRegistrar implements RestRouteRegistrar
{
    public function __construct(private ProviderWebhookController $controller) {}

    public function register(RestRouteRegistry $routes): void
    {
        $routes->registerPublicPost(
            SystemRouteRegistrar::NAMESPACE,
            '/webhooks/(?P<provider>[a-z][a-z0-9_.-]{1,63})',
            $this->controller->ingest(...),
        );
    }
}
