<?php

namespace EventFlow\Presentation\Api;

use EventFlow\Application\Persistence\EventScope;

final readonly class ReceptionLookupInput
{
    public function __construct(public EventScope $scope, public string $code) {}
}
