<?php

namespace EventFlow\Presentation\Api;

use EventFlow\Application\Import\UploadedFile;

final readonly class RestRequest
{
    /** @var array<string, string> */
    private array $headers;

    /** @var array<string, mixed> */
    private array $json;
    /** @var array<string, string> */
    private array $routeParameters;
    private ?string $trustedClientAddress;
    /** @var array<string, string> */
    private array $cookies;
    private bool $trustedSameOrigin;
    /** @var array<string, string> */
    private array $queryParameters;
    private string $rawBody;
    /** @var array<string, UploadedFile> */
    private array $files;

    /** @param array<string, string> $headers @param array<string, mixed> $json @param array<string, string> $routeParameters */
    public function __construct(
        array $headers = [],
        array $json = [],
        array $routeParameters = [],
        ?string $trustedClientAddress = null,
        array $cookies = [],
        bool $trustedSameOrigin = false,
        array $queryParameters = [],
        string $rawBody = '',
        array $files = [],
    )
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
        $this->trustedClientAddress = $trustedClientAddress;
        $normalizedCookies = [];
        foreach ($cookies as $name => $value) {
            if (is_string($name) && is_string($value)) $normalizedCookies[$name] = $value;
        }
        $this->cookies = $normalizedCookies;
        $this->trustedSameOrigin = $trustedSameOrigin;
        $normalizedQuery = [];
        foreach ($queryParameters as $name => $value) {
            if (is_string($name) && (is_string($value) || is_int($value))) $normalizedQuery[$name] = (string) $value;
        }
        $this->queryParameters = $normalizedQuery;
        $this->rawBody = $rawBody;
        $this->files = array_filter($files,static fn(mixed$file):bool=>$file instanceof UploadedFile);
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
    public function clientAddress(): ?string { return $this->trustedClientAddress; }
    public function cookie(string $name): ?string { return $this->cookies[$name] ?? null; }
    public function sameOrigin(): bool { return $this->trustedSameOrigin; }
    public function query(string $name): ?string { return $this->queryParameters[$name] ?? null; }
    /** @return array<string,string> */
    public function queries(): array { return $this->queryParameters; }
    /** @return array<string, string> */
    public function headers(): array { return $this->headers; }
    public function rawBody(): string { return $this->rawBody; }
    public function file(string $name): ?UploadedFile { return $this->files[$name] ?? null; }
}
