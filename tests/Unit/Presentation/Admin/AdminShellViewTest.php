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
        self::assertStringContainsString('id="eventflow-overview"', $html);
        self::assertStringContainsString('aria-label="Event lifecycle actions"', $html);
        self::assertStringContainsString('id="eventflow-event-form"', $html);
        self::assertStringContainsString('id="eventflow-configuration-form"', $html);
        self::assertStringContainsString('id="eventflow-venue-form"', $html);
        self::assertStringContainsString('id="eventflow-setup-notice" role="status"', $html);
        self::assertStringContainsString('id="eventflow-membership-form"', $html);
        self::assertStringContainsString('id="eventflow-invitation-form"', $html);
        self::assertStringContainsString('id="eventflow-attendee-form"', $html);
        self::assertStringContainsString('copy now; it cannot be shown again', $html);
        self::assertStringContainsString('role="tablist"', $html);
        self::assertStringContainsString('id="eventflow-seating"', $html);
        self::assertStringContainsString('id="eventflow-table-form"', $html);
        self::assertStringContainsString('id="eventflow-group-form"', $html);
        self::assertStringContainsString('id="eventflow-placement-form"', $html);
        self::assertStringContainsString('id="eventflow-recommendation-form"', $html);
        self::assertStringContainsString('id="eventflow-seating-notice" role="status"', $html);
        self::assertStringContainsString('id="eventflow-reception"', $html);
        self::assertStringContainsString('id="eventflow-reception-search-form" role="search"', $html);
        self::assertStringContainsString('id="eventflow-reception-notice" role="status"', $html);
        self::assertStringContainsString('id="eventflow-reception-bulk-checkin"', $html);
        self::assertStringContainsString('minlength="2"', $html);
        self::assertStringContainsString('id="eventflow-communications"', $html);
        self::assertStringContainsString('id="eventflow-template-form"', $html);
        self::assertStringContainsString('id="eventflow-campaign-form"', $html);
        self::assertStringContainsString('id="eventflow-message-filter-form"', $html);
        self::assertStringContainsString('aria-label="Communication administration"', $html);
        self::assertStringContainsString('id="eventflow-communications-notice" role="status"', $html);
        self::assertStringContainsString('id="eventflow-governance"', $html);
        self::assertStringContainsString('aria-label="Data and governance administration"', $html);
        self::assertStringContainsString('id="eventflow-import-form"', $html);
        self::assertStringContainsString('enctype="multipart/form-data"', $html);
        self::assertStringContainsString('id="eventflow-export-form"', $html);
        self::assertStringContainsString('id="eventflow-privacy-action-form"', $html);
        self::assertStringContainsString('id="eventflow-audit-filter-form"', $html);
        self::assertStringContainsString('id="eventflow-diagnostics-load"', $html);
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
