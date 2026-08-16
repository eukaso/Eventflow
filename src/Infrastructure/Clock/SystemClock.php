<?php

namespace EventFlow\Infrastructure\Clock;

use DateTimeImmutable;
use DateTimeZone;
use EventFlow\Application\Clock\Clock;

final readonly class SystemClock implements Clock
{
    public function now(): DateTimeImmutable
    {
        return new DateTimeImmutable('now', new DateTimeZone('UTC'));
    }
}
