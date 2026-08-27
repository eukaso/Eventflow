<?php

namespace EventFlow\Infrastructure\WordPress;

use EventFlow\Application\Venue\VenueAuthority;

final readonly class WordPressVenueAuthority implements VenueAuthority
{
    public const CAPABILITY = 'eventflow_manage_venues';

    public function canManageVenues(int $userId): bool
    {
        return $userId > 0
            && function_exists('user_can')
            && (user_can($userId, self::CAPABILITY) || user_can($userId, 'manage_options'));
    }
}
