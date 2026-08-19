<?php

namespace EventFlow\Application\Seating;

final readonly class SeatingGroupReplacement
{
    /** @param list<int> $attendeeIds */
    public function __construct(
        public string $name,
        public string $category,
        public ConstraintLevel $constraintLevel,
        public int $priority,
        public array $attendeeIds,
        public int $expectedRevision,
    ) {
        $allowed = ['family', 'church', 'school', 'work', 'friends', 'association', 'community', 'vip', 'custom'];
        if (trim($name) === '' || strlen(trim($name)) > 190 || !in_array($category, $allowed, true) || $priority < 0 || $priority > 65535 || $attendeeIds === [] || $expectedRevision < 1) {
            throw new SeatingException('seating_group_configuration_invalid');
        }
        $unique = array_values(array_unique($attendeeIds));
        if (count($unique) !== count($attendeeIds)) throw new SeatingException('seating_group_member_invalid');
        foreach ($attendeeIds as $id) if (!is_int($id) || $id < 1) throw new SeatingException('seating_group_member_invalid');
    }
}
