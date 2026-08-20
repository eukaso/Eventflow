<?php

namespace EventFlow\Tests\Unit\Presentation\Admin;

use EventFlow\Bootstrap\{BootstrapResult, BootstrapState};
use EventFlow\Presentation\Admin\AdminShellView;
use PHPUnit\Framework\TestCase;

final class AdminShellViewTest extends TestCase
{
    public function testReadyShellProvidesAccessibleProgressiveEnhancementTargets(): void
    {
        $html = (new AdminShellView())->render(new BootstrapResult(BootstrapState::READY, true, true, []));

        self::assertStringContainsString('id="eventflow-admin"', $html);
        self::assertStringContainsString('data-bootstrap-state="ready"', $html);
        self::assertStringContainsString('data-ready="true"', $html);
        self::assertStringContainsString('aria-live="polite"', $html);
        self::assertStringContainsString('aria-busy="true"', $html);
        self::assertStringContainsString('type="button"', $html);
    }

    public function testNonReadyShellContainsNoBusinessDataOrExecutableMarkup(): void
    {
        $html = (new AdminShellView())->render(new BootstrapResult(
            BootstrapState::MIGRATION_REQUIRED,
            true,
            false,
            ['schema_migration_required'],
        ));

        self::assertStringContainsString('data-bootstrap-state="migration_required"', $html);
        self::assertStringContainsString('data-ready="false"', $html);
        self::assertStringNotContainsString('schema_migration_required', $html);
        self::assertStringNotContainsString('<script', $html);
    }
}
