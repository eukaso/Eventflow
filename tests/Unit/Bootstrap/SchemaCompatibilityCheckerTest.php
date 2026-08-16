<?php

namespace EventFlow\Tests\Unit\Bootstrap;

use EventFlow\Bootstrap\SchemaCompatibility;
use EventFlow\Bootstrap\SchemaCompatibilityChecker;
use PHPUnit\Framework\TestCase;

final class SchemaCompatibilityCheckerTest extends TestCase
{
    public function testCompatibleSchema(): void
    {
        $checker = new SchemaCompatibilityChecker();
        self::assertSame(SchemaCompatibility::COMPATIBLE, $checker->check(3, 3));
    }

    public function testMissingSchemaRequiresMigration(): void
    {
        $checker = new SchemaCompatibilityChecker();
        self::assertSame(SchemaCompatibility::MIGRATION_REQUIRED, $checker->check(3, null));
    }

    public function testOlderSchemaRequiresMigration(): void
    {
        $checker = new SchemaCompatibilityChecker();
        self::assertSame(SchemaCompatibility::MIGRATION_REQUIRED, $checker->check(3, 2));
    }

    public function testNewerSchemaMeansApplicationTooOld(): void
    {
        $checker = new SchemaCompatibilityChecker();
        self::assertSame(SchemaCompatibility::APPLICATION_TOO_OLD, $checker->check(3, 4));
    }
}
