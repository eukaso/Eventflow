<?php

namespace EventFlow\Application\Observability;

final class ObservabilityRedactor
{
    private const REDACTED = '[REDACTED]';
    private const SENSITIVE_TERMS = [
        'authorization', 'cookie', 'credential', 'password', 'secret', 'token', 'session',
        'csrf', 'api_key', 'private_key', 'email', 'phone', 'address', 'recipient',
        'first_name', 'last_name', 'display_name', 'body', 'content', 'reason_message',
        'error_message', 'raw_data', 'normalized_data',
    ];

    /** @param array<string, mixed> $context @return array<string, mixed> */
    public function redact(array $context): array
    {
        return $this->walk($context, 0);
    }

    private function walk(mixed $value, int $depth): mixed
    {
        if ($depth > 12) {
            return '[TRUNCATED]';
        }
        if (is_array($value)) {
            $result = [];
            $count = 0;
            foreach ($value as $key => $item) {
                if (++$count > 100) {
                    $result['_truncated'] = true;
                    break;
                }
                $keyText = (string) $key;
                $normalized = strtolower((string) preg_replace('/[^a-z0-9]+/i', '_', $keyText));
                $safeKey = preg_match('/^[A-Za-z0-9_.-]{1,100}$/', $keyText) === 1 ? $keyText : 'field_' . $count;
                $result[$safeKey] = $this->sensitiveKey($normalized) ? self::REDACTED : $this->walk($item, $depth + 1);
            }
            return $result;
        }
        if (is_string($value)) {
            if (preg_match('//u', $value) !== 1) {
                return '[INVALID_UTF8]';
            }
            if (preg_match('/\bBearer\s+[A-Za-z0-9._~+\/-]+=*|[A-Z0-9._%+-]+@[A-Z0-9.-]+\.[A-Z]{2,}/i', $value)) {
                return self::REDACTED;
            }
            $singleLine = str_replace(["\r", "\n"], ['\\r', '\\n'], $value);
            return strlen($singleLine) > 500 ? substr($singleLine, 0, 500) . '[TRUNCATED]' : $singleLine;
        }
        if ($value === null || is_bool($value) || is_int($value) || (is_float($value) && is_finite($value))) {
            return $value;
        }
        return '[UNSUPPORTED]';
    }

    private function sensitiveKey(string $key): bool
    {
        foreach (self::SENSITIVE_TERMS as $term) {
            if ($key === $term || str_contains($key, $term . '_') || str_contains($key, '_' . $term)) {
                return true;
            }
        }
        return false;
    }
}
