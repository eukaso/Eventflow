<?php

namespace EventFlow\Application\Job;

final class JobPayload
{
    private const MAX_BYTES = 65535;

    /** @param array<string, mixed> $payload */
    public static function validate(array $payload): void
    {
        self::walk($payload, 0);
        $encoded = json_encode($payload, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        if (strlen($encoded) > self::MAX_BYTES) {
            throw new JobException('job_payload_too_large');
        }
    }

    private static function walk(mixed $value, int $depth): void
    {
        if ($depth > 16) {
            throw new JobException('job_payload_too_deep');
        }
        if (is_array($value)) {
            foreach ($value as $key => $item) {
                if (is_string($key) && self::isForbiddenSecretKey($key)) {
                    throw new JobException('job_payload_secret_forbidden');
                }
                self::walk($item, $depth + 1);
            }
            return;
        }
        if ($value === null || is_string($value) || is_int($value) || is_bool($value)) {
            return;
        }
        if (is_float($value) && is_finite($value)) {
            return;
        }
        throw new JobException('job_payload_value_invalid');
    }

    private static function isForbiddenSecretKey(string $key): bool
    {
        $normalized = strtolower((string) preg_replace('/[^a-z0-9]+/i', '_', $key));
        foreach (['_id', '_reference', '_digest', '_hash', '_version', '_expires_at', '_issued_at'] as $safeSuffix) {
            if (str_ends_with($normalized, $safeSuffix)) {
                return false;
            }
        }
        foreach ([
            'authorization', 'cookie', 'credential', 'password', 'secret', 'token',
            'access_token', 'refresh_token', 'api_key', 'private_key',
        ] as $term) {
            if (str_contains($normalized, $term)) {
                return true;
            }
        }
        return false;
    }
}
