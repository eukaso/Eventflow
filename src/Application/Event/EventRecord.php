<?php

namespace EventFlow\Application\Event;

use DateTimeImmutable;
use EventFlow\Application\Persistence\EventScope;

final readonly class EventRecord
{
    public function __construct(
        public EventScope $scope,
        public string $name,
        public string $slug,
        public EventStatus $status,
        public string $timezone,
        public ?DateTimeImmutable $startsAt,
        public ?DateTimeImmutable $endsAt,
        public ?int $venueId,
        public int $revision = 1,
    ) {
        if ($revision < 1) {
            throw new \InvalidArgumentException('event_revision_invalid');
        }
    }
}
