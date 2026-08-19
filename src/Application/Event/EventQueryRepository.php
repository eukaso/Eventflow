<?php

namespace EventFlow\Application\Event;

use DateTimeImmutable;

interface EventQueryRepository
{
    public function listAccessibleForUser(
        int $userId,
        DateTimeImmutable $now,
        int $limit,
        ?int $afterEventId,
    ): EventPage;
}
