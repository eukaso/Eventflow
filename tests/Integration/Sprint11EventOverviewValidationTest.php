<?php

namespace EventFlow\Tests\Integration;

use PHPUnit\Framework\TestCase;

final class Sprint11EventOverviewValidationTest extends TestCase
{
    public function testLifecycleSurfaceMatchesAcceptedEventCommands(): void
    {
        $script = $this->source('assets/admin/eventflow-admin.js');
        foreach (['activate', 'complete', 'cancel', 'archive', 'restore'] as $command) {
            self::assertStringContainsString($command, $script);
        }
        self::assertStringContainsString("method: 'POST'", $script);
        self::assertStringContainsString("'Idempotency-Key': key", $script);
        $lifecycle = strstr($script, 'const transitionEvent =', false);
        self::assertIsString($lifecycle);
        $lifecycle = strstr($lifecycle, 'const field =', true);
        self::assertIsString($lifecycle);
        self::assertStringNotContainsString("'If-Match'", $lifecycle);
    }

    public function testMutationUsesSecureKeysAndReconcilesBeforeRetry(): void
    {
        $script = $this->source('assets/admin/eventflow-admin.js');
        self::assertStringContainsString('window.crypto.getRandomValues', $script);
        self::assertStringNotContainsString('Math.random', $script);
        self::assertStringContainsString('await loadOverview(eventId)', $script);
        self::assertStringContainsString('Refresh before retrying', $script);
        self::assertStringContainsString('button.disabled = true', $script);
    }

    public function testOverviewRetainsSafeDomAndAccessibleStatusPatterns(): void
    {
        $view = $this->source('src/Presentation/Admin/AdminShellView.php');
        self::assertStringContainsString('aria-label="Event lifecycle actions"', $view);
        self::assertStringContainsString('role="status"', $view);

        $script = $this->source('assets/admin/eventflow-admin.js');
        self::assertStringContainsString('textContent', $script);
        self::assertStringNotContainsString('innerHTML', $script);
        self::assertStringNotContainsString('insertAdjacentHTML', $script);
    }

    public function testDashboardMakesGuestProgressAndTargetedRemindersVisible(): void
    {
        $view = $this->source('src/Presentation/Admin/AdminShellView.php');
        foreach (['Event dashboard', 'Needs a reminder', 'Missing companion names', 'Prepare email reminder', 'Prepare SMS reminder', 'Download guest list for Excel', 'Select awaiting RSVP', 'Select missing companion names'] as $expected) {
            self::assertStringContainsString($expected, $view);
        }

        $script = $this->source('assets/admin/eventflow-admin.js');
        foreach (['loadDashboardData', 'invitationProgress', 'dashboardSelectedInvitations', 'Confirmed seats', 'showDashboard(true)', "openCommunications({ channel, recipientIds, reminder: true })", "invitationResponseFilter.value = options.reminder ? 'action_required' : 'all'", 'Review before sending.'] as $expected) {
            self::assertStringContainsString($expected, $script);
        }
        self::assertStringContainsString("response === 'accepted' && activeAttendees.length < capacity", $script);
        self::assertStringContainsString('downloadDashboardGuestList', $script);
        self::assertStringContainsString("type: 'text/csv;charset=utf-8'", $script);
        self::assertStringContainsString('spreadsheetCell', $script);
        self::assertStringContainsString("invitationProgress(invitation).pending", $script);
        self::assertStringContainsString("invitationProgress(invitation).incomplete", $script);
        self::assertStringContainsString('activeConfiguration?.surprise_notice', $script);

        $styles = $this->source('assets/admin/eventflow-admin.css');
        self::assertStringContainsString('.eventflow-dashboard--summary', $styles);
    }

    private function source(string $path): string
    {
        $source = file_get_contents(dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $path));
        self::assertIsString($source, $path);
        return $source;
    }
}
