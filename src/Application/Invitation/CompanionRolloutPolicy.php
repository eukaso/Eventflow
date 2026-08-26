<?php

namespace EventFlow\Application\Invitation;

final class CompanionRolloutPolicy
{
    public const DEFAULT_COMPANION_LIMIT = 1;
    public const DEFAULT_TOTAL_CAPACITY = self::DEFAULT_COMPANION_LIMIT + 1;

    public static function importedCapacity(int $requestedCapacity): int
    {
        return min($requestedCapacity, self::DEFAULT_TOTAL_CAPACITY);
    }
}
