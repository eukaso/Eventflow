<?php

namespace EventFlow\Application\Event;

final readonly class EventPage
{
    /** @param list<EventRecord> $events */
    public function __construct(
        public array $events,
        public ?int $nextAfterEventId,
    ) {
    }
}
