<?php

namespace EventFlow\Application\Error;

use InvalidArgumentException;

final readonly class VersionConflictDetails implements PublicErrorDetails
{
    public function __construct(public int $expectedVersion, public int $currentVersion)
    {
        if ($expectedVersion < 0 || $currentVersion < 0) {
            throw new InvalidArgumentException('invalid_version_conflict_details');
        }
    }

    public function kind(): ErrorDetailKind
    {
        return ErrorDetailKind::VERSION_CONFLICT;
    }

    public function toArray(): array
    {
        return ['expected_version' => $this->expectedVersion, 'current_version' => $this->currentVersion];
    }
}
