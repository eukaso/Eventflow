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
        $this->defineFoundationConstants();
        $GLOBALS['wpdb'] = new class {
            public string $prefix = 'wp_';

            public function prepare(string $query, mixed ...$values): string
            {
                return vsprintf(str_replace(['%s', '%d'], ["'%s'", '%d'], $query), $values);
            }

            public function esc_like(string $value): string
            {
                return $value;
            }

            public function get_var(string $query): mixed
            {
                return str_starts_with($query, 'SHOW TABLES') ? 'wp_eventflow_schema_migrations' : '1';
            }
        };

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
        $this->defineFoundationConstants();
        $GLOBALS['wpdb'] = new class {
            public string $prefix = 'wp_';

            public function prepare(string $query, mixed ...$values): string
            {
                return vsprintf(str_replace(['%s', '%d'], ["'%s'", '%d'], $query), $values);
            }

            public function esc_like(string $value): string
            {
                return $value;
            }

            public function get_var(string $query): mixed
            {
                return str_starts_with($query, 'SHOW TABLES') ? 'wp_eventflow_schema_migrations' : '2';
            }
        };

        $result = ApplicationBootstrap::boot();

        self::assertSame(BootstrapState::INCOMPATIBLE_SCHEMA, $result->state);
        self::assertTrue($result->healthy);
        self::assertFalse($result->ready);
        self::assertSame(['application_schema_incompatible'], $result->codes);
    }

    private function defineFoundationConstants(): void
    {
        define('EVENTFLOW_VERSION', '0.9.0');
        define('EVENTFLOW_SCHEMA_VERSION', 1);
    }
}
