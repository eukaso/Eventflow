<?php

namespace EventFlow\Tests\Integration;

use PHPUnit\Framework\TestCase;

final class Sprint11SeatingWorkspaceValidationTest extends TestCase
{
    public function testWorkspaceUsesOnlyAcceptedEventScopedSeatingRoutes(): void
    {
        $script = $this->source('assets/admin/eventflow-admin.js');
        foreach (['/tables', '/seating-groups', '/seating/readiness', '/seating/move', '/seating/recommendations'] as $route) {
            self::assertStringContainsString($route, $script);
        }
        self::assertStringNotContainsString('wp_eventflow_', $script);
    }

    public function testEverySeatingMutationUsesCryptographicIdempotency(): void
    {
        $script = $this->source('assets/admin/eventflow-admin.js');
        self::assertStringContainsString('const runSeatingMutation', $script);
        self::assertStringContainsString('headers: mutationHeaders(etag)', $script);
        self::assertStringContainsString('window.crypto.getRandomValues', $script);
        self::assertStringNotContainsString('Math.random', $script);
    }

    public function testRecommendationRequiresExplicitReviewBeforeApply(): void
    {
        $script = $this->source('assets/admin/eventflow-admin.js');
        self::assertStringContainsString('Recommendation generated for review. It has not been applied.', $script);
        self::assertStringContainsString('Apply reviewed recommendation', $script);
        self::assertStringContainsString("window.confirm('Apply this reviewed recommendation", $script);
        self::assertStringContainsString('/apply`, {}', $script);
    }

    public function testWorkspaceProvidesAccessibleNonDragAlternativesAndSafeRendering(): void
    {
        $view = $this->source('src/Presentation/Admin/AdminShellView.php');
        foreach (['Manual placement', 'Place attendee', 'role="status"', 'Close seating workspace'] as $expected) {
            self::assertStringContainsString($expected, $view);
        }
        $script = $this->source('assets/admin/eventflow-admin.js');
        self::assertStringContainsString('textContent', $script);
        foreach (['innerHTML', 'insertAdjacentHTML', 'document.write', 'eval('] as $forbidden) {
            self::assertStringNotContainsString($forbidden, $script);
        }
    }

    public function testPartialReadFailuresRemainIsolated(): void
    {
        $script = $this->source('assets/admin/eventflow-admin.js');
        self::assertStringContainsString('Promise.allSettled([', $script);
        foreach (['Tables unavailable.', 'Groups unavailable.', 'Attendees unavailable.', 'Readiness unavailable.'] as $message) {
            self::assertStringContainsString($message, $script);
        }
    }

    private function source(string $path): string
    {
        $source = file_get_contents(dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $path));
        self::assertIsString($source, $path);
        return $source;
    }
}
