<?php

namespace EventFlow\Application\Seating;

final readonly class SeatingSnapshot
{
    /** @param list<SeatingAttendee> $attendees @param list<SeatingTable> $tables @param list<SeatingSeat> $seats @param list<SeatingGroup> $groups @param list<SeatingAssignment> $assignments */
    public function __construct(
        public array $attendees,
        public array $tables,
        public array $seats,
        public array $groups,
        public array $assignments,
    ) {}

    public function fingerprint(): string
    {
        $values = [];
        foreach ($this->attendees as $a) $values[] = ['a', $a->attendeeId, $a->requiresAccessibleSeat];
        foreach ($this->tables as $t) $values[] = ['t', $t->tableId, $t->capacity, $t->sortOrder];
        foreach ($this->seats as $s) $values[] = ['s', $s->seatId, $s->tableId, $s->accessible, $s->sortOrder];
        foreach ($this->groups as $g) $values[] = ['g', $g->groupId, $g->constraintLevel->value, $g->priority, $g->attendeeIds];
        foreach ($this->assignments as $a) $values[] = ['x', $a->assignmentId, $a->attendeeId, $a->tableId, $a->seatId, $a->source];
        return hash('sha256', (string) json_encode($values, JSON_THROW_ON_ERROR));
    }
}
