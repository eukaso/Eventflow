<?php

namespace EventFlow\Application\Idempotency;

use JsonException;

final class CanonicalRequestHasher
{
    public function hash(mixed $request): string
    {
        try {
            $canonical = json_encode(
                $this->normalize($request),
                JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRESERVE_ZERO_FRACTION,
            );
        } catch (JsonException $exception) {
            throw new IdempotencyException('request_fingerprint_invalid', $exception);
        }

        return hash('sha256', $canonical);
    }

    private function normalize(mixed $value): mixed
    {
        if ($value === null || is_bool($value) || is_int($value) || is_string($value)) {
            return $value;
        }

        if (is_float($value)) {
            if (!is_finite($value)) {
                throw new IdempotencyException('request_fingerprint_invalid');
            }

            return $value;
        }

        if (!is_array($value)) {
            throw new IdempotencyException('request_fingerprint_invalid');
        }

        if (array_is_list($value)) {
            return array_map(fn (mixed $item): mixed => $this->normalize($item), $value);
        }

        $normalized = [];
        ksort($value, SORT_STRING);

        foreach ($value as $key => $item) {
            $normalized[(string) $key] = $this->normalize($item);
        }

        return $normalized;
    }
}
