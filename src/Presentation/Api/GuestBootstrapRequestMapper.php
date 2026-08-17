<?php

namespace EventFlow\Presentation\Api;

final readonly class GuestBootstrapRequestMapper
{
    public function credential(RestRequest $request): string
    {
        $json = $request->json();
        if (array_diff(array_keys($json), ['credential']) !== []) {
            throw new RequestInputException('validation_failed');
        }
        $credential = $json['credential'] ?? null;
        if (!is_string($credential) || !preg_match('/^[a-f0-9]{64}$/', $credential)) {
            throw new RequestInputException('guest_session_invalid');
        }
        return $credential;
    }
}
