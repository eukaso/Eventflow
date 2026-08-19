<?php

namespace EventFlow\Application\Seating;

final readonly class SeatingGroup
{
    /** @param list<int> $attendeeIds */
    public function __construct(
        public int $groupId,
        public string $name,
        public ConstraintLevel $constraintLevel,
        public int $priority,
        public array $attendeeIds,
        public string $category = 'custom',
        public string $source = 'host_defined',
        public int $revision = 1,
    ) {
        if ($groupId < 1 || trim($name) === '' || $priority < 0 || trim($category) === '' || trim($source) === '' || $revision < 1) throw new SeatingException('seating_group_invalid');
        foreach ($attendeeIds as $id) if (!is_int($id) || $id < 1) throw new SeatingException('seating_group_member_invalid');
    }
}
