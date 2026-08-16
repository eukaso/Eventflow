<?php

namespace EventFlow\Infrastructure\WordPress;

use EventFlow\Application\Authorization\GlobalRecoveryAuthority;

final readonly class WordPressGlobalRecoveryAuthority implements GlobalRecoveryAuthority
{
    public const CAPABILITY = 'eventflow_recover_primary_owner';

    public function canRecoverPrimaryOwnership(int $userId): bool
    {
        return $userId > 0
            && function_exists('user_can')
            && user_can($userId, self::CAPABILITY);
    }
}
