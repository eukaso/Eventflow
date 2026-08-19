<?php

namespace EventFlow\Presentation\Api;

use EventFlow\Application\Persistence\EventScope;

final readonly class SeatingSeatInput
{
    public function __construct(
        public EventScope $scope,
        public int $tableId,
        public string $label,
        public bool $accessible,
        public int $sortOrder,
    ) {
    }
}
