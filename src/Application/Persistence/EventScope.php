<?php

namespace EventFlow\Application\Persistence;

use InvalidArgumentException;

final readonly class EventScope
{
    public function __construct(public int $eventId)
    {
        if ($eventId < 1) {
            throw new InvalidArgumentException('invalid_event_scope');
        }
    }
}
