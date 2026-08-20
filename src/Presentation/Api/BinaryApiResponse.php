<?php

namespace EventFlow\Presentation\Api;

final readonly class BinaryApiResponse implements ApiResponse
{
    /** @param array<string,string> $responseHeaders */
    public function __construct(
        private string $content,
        private array $responseHeaders,
        private int $statusCode = 200,
    ) {}

    public function status(): int { return $this->statusCode; }
    public function body(): array { return []; }
    public function headers(): array { return $this->responseHeaders; }
    public function content(): string { return $this->content; }
}
