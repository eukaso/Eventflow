<?php

namespace EventFlow\Presentation\Api;

use EventFlow\Application\Authorization\PrincipalContext;
use EventFlow\Application\Error\RequestId;

final readonly class GuestAuthenticatedRequestContext
{
    public function __construct(
        public PrincipalContext $principal,
        public RequestId $requestId,
    ) {
    }
}
