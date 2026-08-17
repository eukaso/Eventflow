<?php

namespace EventFlow\Presentation\Api;

final readonly class JsonApiResponse implements ApiResponse
{
    /** @param array<string, mixed> $payload @param array<string, string> $responseHeaders */
    public function __construct(
        private int $statusCode,
        private array $payload,
        private array $responseHeaders,
    ) {
    }

    public function status(): int { return $this->statusCode; }
    public function body(): array { return $this->payload; }
    public function headers(): array { return $this->responseHeaders; }
}
