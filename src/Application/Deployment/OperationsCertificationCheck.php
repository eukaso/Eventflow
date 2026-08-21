<?php

namespace EventFlow\Application\Deployment;

final readonly class OperationsCertificationCheck
{
    public function __construct(public string $identifier, public string $status, public string $code) {}

    public function toArray(): array
    {
        return ['identifier' => $this->identifier, 'status' => $this->status, 'code' => $this->code];
    }
}
