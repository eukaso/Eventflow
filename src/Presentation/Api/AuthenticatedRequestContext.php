<?php

namespace EventFlow\Presentation\Api;

use EventFlow\Application\Authorization\PrincipalContext;
use EventFlow\Application\Error\RequestId;
use LogicException;

final readonly class AuthenticatedRequestContext
{
    public function __construct(
        public PrincipalContext $principal,
        public RequestId $requestId,
        public ?string $idempotencyKey,
        public ?int $expectedVersion,
    ) {
    }

    public function requiredIdempotencyKey(): string
    {
        return $this->idempotencyKey ?? throw new LogicException('idempotency_key_not_required_by_policy');
    }

    public function requiredExpectedVersion(): int
    {
        return $this->expectedVersion ?? throw new LogicException('if_match_not_required_by_policy');
    }
}
