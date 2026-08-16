<?php

namespace EventFlow\Application\Audit;

final class AuditPayloadRedactor
{
    private const REDACTED = '[REDACTED]';

    /** @var list<string> */
    private const SECRET_TERMS = [
        'authorization', 'cookie', 'credential', 'password', 'secret', 'token', 'session_token',
        'access_token', 'refresh_token', 'api_key', 'private_key', 'raw_token', 'token_digest',
    ];

    /** @param array<string, mixed>|null $payload @return array<string, mixed>|null */
    public function redact(?array $payload): ?array
    {
        if ($payload === null) {
            return null;
        }

        $redacted = $this->walk($payload, 0);
        $encoded = json_encode($redacted, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

        if (strlen($encoded) > 65535) {
            throw new AuditException('audit_payload_too_large');
        }

        return $redacted;
    }

    private function walk(mixed $value, int $depth): mixed
    {
        if ($depth > 16) {
            throw new AuditException('audit_payload_too_deep');
        }

        if (is_array($value)) {
            $result = [];
            foreach ($value as $key => $item) {
                if (is_string($key) && $this->isSecretKey($key)) {
                    $result[$key] = self::REDACTED;
                    continue;
                }
                $result[$key] = $this->walk($item, $depth + 1);
            }
            return $result;
        }

        if ($value === null || is_string($value) || is_int($value) || is_bool($value)) {
            return $value;
        }

        if (is_float($value) && is_finite($value)) {
            return $value;
        }

        throw new AuditException('audit_payload_value_invalid');
    }

    private function isSecretKey(string $key): bool
    {
        $normalized = strtolower((string) preg_replace('/[^a-z0-9]+/i', '_', $key));
        foreach (self::SECRET_TERMS as $term) {
            if (str_contains($normalized, $term)) {
                return true;
            }
        }
        return false;
    }
}
