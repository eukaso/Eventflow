<?php

namespace EventFlow\Presentation\Api;

final readonly class RestRequest
{
    /** @var array<string, string> */
    private array $headers;

    /** @param array<string, string> $headers */
    public function __construct(array $headers = [])
    {
        $normalized = [];
        foreach ($headers as $name => $value) {
            if (!is_string($name) || !is_string($value)) {
                continue;
            }
            $normalized[strtolower(trim($name))] = trim($value);
        }
        $this->headers = $normalized;
    }

    public function header(string $name): ?string
    {
        $value = $this->headers[strtolower(trim($name))] ?? null;
        return $value === '' ? null : $value;
    }
}
