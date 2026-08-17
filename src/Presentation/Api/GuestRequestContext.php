<?php

namespace EventFlow\Presentation\Api;

use EventFlow\Application\Authorization\PrincipalContext;
use EventFlow\Application\Error\RequestId;

final readonly class GuestRequestContext
{
    public function __construct(
        public PrincipalContext $principal,
        public RequestId $requestId,
        public string $idempotencyKey,
        public int $expectedRevision,
    ) {
    }
}
