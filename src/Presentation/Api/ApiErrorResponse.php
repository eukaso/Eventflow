<?php

namespace EventFlow\Presentation\Api;

final readonly class ApiErrorResponse
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
}
