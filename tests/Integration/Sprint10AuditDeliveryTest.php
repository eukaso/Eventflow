<?php

namespace EventFlow\Tests\Integration;

use PHPUnit\Framework\TestCase;

final class Sprint10AuditDeliveryTest extends TestCase
{
    public function testReadOnlyRoutesAndReadyModeCompositionAreComplete(): void
    {
        $routes = $this->source('src/Presentation/Api/AuditRouteRegistrar.php');
        self::assertSame(3, substr_count($routes, 'registerAuthenticatedGet'));
        self::assertStringNotContainsString('registerAuthenticatedPost', $routes);
        foreach (["'/events/(?P<event_id>\\d+)/audit'", ".'/integrity'", 'audit_log_id'] as $expected) self::assertStringContainsString($expected, $routes);
        $bootstrap = $this->source('src/Bootstrap/ApplicationBootstrap.php');
        self::assertStringContainsString('$container->database->auditAccess', $bootstrap);
        self::assertStringContainsString('new AuditRouteRegistrar($audit)', $bootstrap);
    }

    public function testStrictMinimizedNoStorePresentationBoundariesAreExplicit(): void
    {
        $mapper = $this->source('src/Presentation/Api/AuditRequestMapper.php');
        foreach (['PAGE_FIELDS', 'array_diff', 'occurred_from', 'occurred_until', 'createFromFormat'] as $expected) self::assertStringContainsString($expected, $mapper);
        $presenter = $this->source('src/Presentation/Api/AuditPresenter.php');
        foreach (['private, no-store', "'failure_code'", "'before'", "'after'"] as $expected) self::assertStringContainsString($expected, $presenter);
        $summaryStart = strpos($presenter, 'private function summary');
        $detailStart = strpos($presenter, 'private function detail');
        self::assertIsInt($summaryStart); self::assertIsInt($detailStart);
        $summary = substr($presenter, $summaryStart, $detailStart - $summaryStart);
        self::assertStringNotContainsString("'before'", $summary);
        self::assertStringNotContainsString("'after'", $summary);
    }

    private function source(string $relative): string
    {
        $contents=file_get_contents(dirname(__DIR__,2).'/'.$relative);
        self::assertIsString($contents);
        return $contents;
    }
}
