<?php

namespace EventFlow\Presentation\Api;

final readonly class ProviderWebhookRequestMapper
{
    /** @return array{provider: string, headers: array<string, string>, raw_body: string, context: array<string,string>} */
    public function ingress(RestRequest $request): array
    {
        $provider = $request->route('provider');
        if ($provider === null || !preg_match('/^[a-z][a-z0-9_.-]{1,63}$/', $provider)) {
            throw new RequestInputException('resource_not_found');
        }
        return ['provider' => $provider, 'headers' => $request->headers(), 'raw_body' => $request->rawBody(), 'context' => $request->queries()];
    }
}
