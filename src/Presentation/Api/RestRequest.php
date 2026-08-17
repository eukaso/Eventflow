<?php

namespace EventFlow\Presentation\Api;

final readonly class RestRequest
{
    /** @var array<string, string> */
    private array $headers;

    /** @var array<string, mixed> */
    private array $json;
    /** @var array<string, string> */
    private array $routeParameters;

    /** @param array<string, string> $headers @param array<string, mixed> $json @param array<string, string> $routeParameters */
    public function __construct(array $headers = [], array $json = [], array $routeParameters = [])
    {
        $normalized = [];
        foreach ($headers as $name => $value) {
            if (!is_string($name) || !is_string($value)) {
                continue;
            }
            $normalized[strtolower(trim($name))] = trim($value);
        }
        $this->headers = $normalized;
        $this->json = $json;
        $this->routeParameters = $routeParameters;
    }

    public function header(string $name): ?string
    {
        $value = $this->headers[strtolower(trim($name))] ?? null;
        return $value === '' ? null : $value;
    }

    /** @return array<string, mixed> */
    public function json(): array { return $this->json; }
    public function input(string $name): mixed { return $this->json[$name] ?? null; }
    public function route(string $name): ?string { return $this->routeParameters[$name] ?? null; }
}
