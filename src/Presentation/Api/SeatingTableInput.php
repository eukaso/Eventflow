<?php

namespace EventFlow\Presentation\Api;

use EventFlow\Application\Persistence\EventScope;

final readonly class SeatingTableInput
{
    /** @param list<array{label:string, accessible:bool}> $seats */
    public function __construct(
        public EventScope $scope,
        public string $name,
        public int $capacity,
        public array $seats,
    ) {
    }
}
