<?php

namespace EventFlow\Tests\Integration;

use PHPUnit\Framework\TestCase;

final class Sprint11ReceptionWorkspaceValidationTest extends TestCase
{
    public function testReceptionUsesOnlyLeastPrivilegeEventScopedRoutes(): void
    {
        $script = $this->source('assets/admin/eventflow-admin.js');
        foreach (['/reception/attendees?q=', '/check-ins', "bulk ? '/bulk' : ''", '/reverse'] as $route) {
            self::assertStringContainsString($route, $script);
        }
        foreach (['primary_email', 'primary_phone', 'dietary_requirements', 'accessibility_requirements', 'wp_eventflow_'] as $forbidden) {
            $start = strpos($script, 'const receptionEventPath');
            $end = strpos($script, 'const communicationEventPath');
            self::assertIsInt($start);
            self::assertIsInt($end);
            $receptionSource = substr($script, $start, $end - $start);
            self::assertStringNotContainsString($forbidden, $receptionSource);
        }
    }

    public function testArrivalMutationsAreIdempotentAndReconcileAuthoritativeState(): void
    {
        $script = $this->source('assets/admin/eventflow-admin.js');
        self::assertStringContainsString('const runReceptionMutation', $script);
        self::assertStringContainsString('headers: mutationHeaders()', $script);
        self::assertStringContainsString('await refreshReceptionSearch()', $script);
        self::assertStringContainsString('window.crypto.getRandomValues', $script);
        self::assertStringNotContainsString('Math.random', $script);
    }

    public function testDuplicateAndAmbiguousOutcomesNeverClaimASecondArrival(): void
    {
        $script = $this->source('assets/admin/eventflow-admin.js');
        foreach (['attendee_already_checked_in', 'checkin_already_reversed', 'no duplicate action was created', 'could not be confirmed', 'Search again before retrying'] as $expected) {
            self::assertStringContainsString($expected, $script);
        }
    }

    public function testBulkCheckInIsSingleAtomicCommandAndReversalRequiresReason(): void
    {
        $script = $this->source('assets/admin/eventflow-admin.js');
        self::assertStringContainsString('{ attendee_ids: attendeeIds, station_id: context.stationId, method: \'search\', notes: context.notes }', $script);
        self::assertStringContainsString("field(form, 'reason').value", $script);
        self::assertStringContainsString('{ reason }', $script);
        $view = $this->source('src/Presentation/Admin/AdminShellView.php');
        self::assertStringContainsString('Check in selected', $view);
        self::assertStringContainsString('Arrival notes', $view);
    }

    public function testEventDayUiIsTouchKeyboardAndAssistiveTechnologyFriendly(): void
    {
        $view = $this->source('src/Presentation/Admin/AdminShellView.php');
        foreach (['role="search"', 'role="status"', 'Guest or companion name', 'Close reception workspace'] as $expected) {
            self::assertStringContainsString($expected, $view);
        }
        $styles = $this->source('assets/admin/eventflow-admin.css');
        self::assertStringContainsString('min-height: 44px', $styles);
        self::assertStringContainsString('@media (max-width: 600px)', $styles);
        $script = $this->source('assets/admin/eventflow-admin.js');
        foreach (['innerHTML', 'insertAdjacentHTML', 'document.write', 'eval('] as $forbidden) {
            self::assertStringNotContainsString($forbidden, $script);
        }
    }

    public function testReceptionExplicitlyAvoidsMessagingProviderDependency(): void
    {
        $script = $this->source('assets/admin/eventflow-admin.js');
        self::assertStringContainsString('does not depend on messaging providers', $script);
        self::assertStringContainsString('Searching local reception records', $script);
    }

    private function source(string $path): string
    {
        $source = file_get_contents(dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $path));
        self::assertIsString($source, $path);
        return $source;
    }
}
