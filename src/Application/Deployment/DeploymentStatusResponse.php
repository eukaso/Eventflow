<?php

namespace EventFlow\Application\Deployment;

use InvalidArgumentException;

final readonly class DeploymentStatusResponse
{
    /** @param array<string, string> $headers @param array<string, mixed> $body */
    public function __construct(
        public int $status,
        public array $headers,
        public array $body,
    ) {
        if ($status < 100 || $status > 599) {
            throw new InvalidArgumentException('invalid_deployment_status_response');
        }
    }

    public function header(string $name): ?string
    {
        return $this->headers[strtolower($name)] ?? null;
    }
}
