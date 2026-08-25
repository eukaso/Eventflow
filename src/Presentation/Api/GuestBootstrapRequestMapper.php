<?php

namespace EventFlow\Presentation\Api;

use EventFlow\Application\GuestAccess\GuestCredentialType;

final readonly class GuestBootstrapRequestMapper
{
    public function credential(RestRequest $request): string
    {
        $json = $request->json();
        if (array_diff(array_keys($json), ['credential', 'credential_type']) !== []) {
            throw new RequestInputException('validation_failed');
        }
        $credential = $json['credential'] ?? null;
        if (!is_string($credential) || !preg_match('/^[a-f0-9]{64}$/', $credential)) {
            throw new RequestInputException('guest_session_invalid');
        }
        return $credential;
    }

    public function credentialType(RestRequest $request): ?GuestCredentialType
    {
        $value = $request->json()['credential_type'] ?? null;
        if ($value === null) return null;
        if (!is_string($value) || ($type = GuestCredentialType::tryFrom($value)) === null) {
            throw new RequestInputException('guest_session_invalid');
        }
        return $type;
    }
}
