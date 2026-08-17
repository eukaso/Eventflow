<?php

namespace EventFlow\Presentation\Api;

final readonly class ApiErrorResponse implements ApiResponse
{
    /**
     * @param array{code: string, message: string, data: array<string, mixed>} $body
     * @param array<string, string> $headers
     */
    public function __construct(
        public int $status,
        public array $body,
        public array $headers,
    ) {
    }

    public function status(): int { return $this->status; }
    public function body(): array { return $this->body; }
    public function headers(): array { return $this->headers; }
}
