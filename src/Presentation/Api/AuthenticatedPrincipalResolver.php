<?php

namespace EventFlow\Presentation\Api;

use EventFlow\Application\Authorization\PrincipalContext;

interface AuthenticatedPrincipalResolver
{
    public function resolve(RestRequest $request): PrincipalContext;
}
