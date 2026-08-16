<?php

namespace EventFlow\Tests\Unit\Bootstrap;

use EventFlow\Bootstrap\ApplicationBootstrap;
use EventFlow\Bootstrap\BootstrapState;
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use PHPUnit\Framework\TestCase;

final class ApplicationBootstrapTest extends TestCase
{
    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function testCompatibleSchemaRegistersFullModeIdempotently(): void
    {
        $this->defineFoundationConstants(1);

        $first = ApplicationBootstrap::boot();
        $second = ApplicationBootstrap::boot();

        self::assertSame($first, $second);
        self::assertSame(BootstrapState::READY, $first->state);
        self::assertTrue($first->healthy);
        self::assertTrue($first->ready);
        self::assertSame([], $first->codes);
    }

    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function testMissingSchemaRegistersMigrationOnlyMode(): void
    {
        $this->defineFoundationConstants();

        $result = ApplicationBootstrap::boot();

        self::assertSame(BootstrapState::MIGRATION_REQUIRED, $result->state);
        self::assertTrue($result->healthy);
        self::assertFalse($result->ready);
        self::assertSame(['schema_migration_required'], $result->codes);
    }

    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function testNewerSchemaFailsClosedInMinimalMode(): void
    {
        $this->defineFoundationConstants(2);

        $result = ApplicationBootstrap::boot();

        self::assertSame(BootstrapState::INCOMPATIBLE_SCHEMA, $result->state);
        self::assertTrue($result->healthy);
        self::assertFalse($result->ready);
        self::assertSame(['application_schema_incompatible'], $result->codes);
    }

    private function defineFoundationConstants(?int $installedSchemaVersion = null): void
    {
        define('EVENTFLOW_VERSION', '0.8.0-dev');
        define('EVENTFLOW_SCHEMA_VERSION', 1);

        if ($installedSchemaVersion !== null) {
            define('EVENTFLOW_INSTALLED_SCHEMA_VERSION', $installedSchemaVersion);
        }
    }
}
