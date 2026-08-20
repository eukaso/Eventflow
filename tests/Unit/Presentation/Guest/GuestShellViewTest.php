<?php

namespace EventFlow\Tests\Unit\Presentation\Guest;

use EventFlow\Bootstrap\{BootstrapResult, BootstrapState};
use EventFlow\Presentation\Guest\GuestShellView;
use PHPUnit\Framework\TestCase;

final class GuestShellViewTest extends TestCase
{
    public function testReadyShellProvidesAccessibleRsvpStructureWithoutEmbeddedGuestData(): void
    {
        $html = (new GuestShellView())->render(new BootstrapResult(BootstrapState::READY, true, true, []));

        self::assertStringContainsString('id="eventflow-guest"', $html);
        self::assertStringContainsString('data-ready="true"', $html);
        self::assertStringContainsString('aria-busy="true"', $html);
        self::assertStringContainsString('id="eventflow-rsvp-form"', $html);
        self::assertStringContainsString('role="status"', $html);
        self::assertStringContainsString('type="radio" value="accepted"', $html);
        self::assertStringContainsString('type="radio" value="declined"', $html);
        self::assertStringNotContainsString('<script', $html);
        self::assertStringNotContainsString('credential', strtolower($html));
        self::assertStringNotContainsString('csrf', strtolower($html));
    }

    public function testNonReadyShellExposesOnlyNonSensitiveBootstrapState(): void
    {
        $html = (new GuestShellView())->render(new BootstrapResult(BootstrapState::MIGRATION_REQUIRED, true, false, ['schema_migration_required']));
        self::assertStringContainsString('data-bootstrap-state="migration_required"', $html);
        self::assertStringContainsString('data-ready="false"', $html);
        self::assertStringNotContainsString('schema_migration_required', $html);
    }
}
