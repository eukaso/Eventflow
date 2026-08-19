<?php

namespace EventFlow\Application\Seating;

final readonly class SeatingGroupMove
{
    /** @param list<SeatingAssignment> $assignments */
    public function __construct(
        public int $groupId,
        public int $tableId,
        public array $assignments,
        public bool $requiredGroupOverride = false,
        public ?string $overrideReason = null,
    ) {
        if ($groupId < 1 || $tableId < 1 || $assignments === []) throw new SeatingException('seating_group_move_invalid');
    }
}
