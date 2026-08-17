<?php

namespace EventFlow\Bootstrap;

use EventFlow\Presentation\Api\AuthenticatedRequestContextFactory;

final readonly class DeliveryServices
{
    public function __construct(public AuthenticatedRequestContextFactory $authenticatedRequests)
    {
    }
}
