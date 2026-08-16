<?php

namespace EventFlow\Bootstrap;

final class SchemaCompatibilityChecker
{
    public function check(int $expected, ?int $installed): SchemaCompatibility
    {
        if ($installed === null) {
            return SchemaCompatibility::MIGRATION_REQUIRED;
        }

        if ($installed === $expected) {
            return SchemaCompatibility::COMPATIBLE;
        }

        if ($installed < $expected) {
            return SchemaCompatibility::MIGRATION_REQUIRED;
        }

        return SchemaCompatibility::APPLICATION_TOO_OLD;
    }
}
