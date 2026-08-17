<?php

namespace EventFlow\Presentation\Api;

use EventFlow\Application\Persistence\EventScope;

final readonly class ReceptionSearchInput
{
    public function __construct(public EventScope $scope, public string $query, public int $limit) {}
}
