<?php

namespace EventFlow\Application\Audit;

final class AuditCanonicalizer
{
    public const VERSION = 1;

    /** @param array<string, mixed> $payload */
    public function canonicalize(array $payload, int $version = self::VERSION): string
    {
        if ($version !== self::VERSION) {
            throw new AuditException('audit_canonicalization_version_unsupported');
        }

        return json_encode(
            $this->normalize($payload),
            JSON_THROW_ON_ERROR | JSON_PRESERVE_ZERO_FRACTION | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE,
        );
    }

    private function normalize(mixed $value): mixed
    {
        if (!is_array($value)) {
            return $value;
        }

        if (!array_is_list($value)) {
            ksort($value, SORT_STRING);
        }

        foreach ($value as $key => $item) {
            $value[$key] = $this->normalize($item);
        }

        return $value;
    }

    public function hash(AuditRecord $record): string
    {
        return hash('sha256', $this->canonicalize(
            $record->canonicalPayload(),
            $record->canonicalizationVersion,
        ));
    }
}
