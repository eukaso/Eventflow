<?php

namespace EventFlow\Application\EventConfiguration;

use EventFlow\Application\Persistence\EventScope;
use InvalidArgumentException;

final readonly class EventConfigurationRecord
{
    public function __construct(public EventScope $eventScope, public EventConfigurationAttributes $attributes, public int $revision)
    {
        if ($revision < 1) throw new InvalidArgumentException('event_configuration_record_invalid');
    }
}
