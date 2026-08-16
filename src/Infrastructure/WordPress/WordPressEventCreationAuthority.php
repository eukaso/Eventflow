<?php

namespace EventFlow\Infrastructure\WordPress;

use EventFlow\Application\Event\EventCreationAuthority;

final readonly class WordPressEventCreationAuthority implements EventCreationAuthority
{
    public const CAPABILITY = 'eventflow_create_events';

    public function canCreateEvent(int $userId): bool
    {
        return $userId > 0
            && function_exists('user_can')
            && user_can($userId, self::CAPABILITY);
    }
}
