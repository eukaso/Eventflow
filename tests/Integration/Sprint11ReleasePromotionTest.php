<?php

namespace EventFlow\Tests\Integration;

use PHPUnit\Framework\TestCase;

final class Sprint11ReleasePromotionTest extends TestCase
{
    public function testStableVersionPromotionMatchesAcceptedRelease(): void
    {
        $release = $this->source('docs/11-releases/1.2.0-sprint-11-ui-experience.md');
        foreach (['**Status:** Released', '**Release tag:** `v1.2.0-ui-experience`', 'run 32430086979', 'EventFlow schema: 15'] as $expected) {
            self::assertStringContainsString($expected, $release);
        }

        $acceptance = $this->source('docs/10-testing/Sprint-11-UI-UX-Acceptance-Report.md');
        self::assertStringContainsString('Result: PASS', $acceptance);
        self::assertStringContainsString('PHP 8.2 and PHP 8.3 PASS', $acceptance);
        self::assertStringContainsString('run 32430086979', $acceptance);
        self::assertStringContainsString('## [1.2.0] - 2026-08-20', $this->source('CHANGELOG.md'));

        $plugin = $this->source('eventflow.php');
        self::assertMatchesRegularExpression('/Version: (?:1\\.2\\.0|1\\.[3-9]\\.[0-9]+-dev)/', $plugin);
        self::assertMatchesRegularExpression("/define\\('EVENTFLOW_VERSION', '(?:1\\.2\\.0|1\\.[3-9]\\.[0-9]+-dev)'\\);/", $plugin);
        self::assertStringContainsString("define('EVENTFLOW_SCHEMA_VERSION', 15);", $plugin);
    }

    public function testAccessibilityResponsiveAndWordPressEvidenceRemainsLinked(): void
    {
        $release = $this->source('docs/11-releases/1.2.0-sprint-11-ui-experience.md');
        foreach (['docs/10-testing/Sprint-11-UI-UX-Acceptance-Report.md', 'docs/07-ui-ux/EF-DOC-009-EventFlow-UI-UX-Design-v1.0.md', 'tests/Integration/Sprint11ReleasePromotionTest.php'] as $path) {
            self::assertFileExists($this->root($path));
            self::assertStringContainsString($path, $release);
        }
    }

    private function source(string $path): string
    {
        $source = file_get_contents($this->root($path));
        self::assertIsString($source, $path);
        return $source;
    }

    private function root(string $path): string
    {
        return dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $path);
    }
}
