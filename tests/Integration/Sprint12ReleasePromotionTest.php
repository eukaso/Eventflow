<?php

namespace EventFlow\Tests\Integration;

use PHPUnit\Framework\TestCase;

final class Sprint12ReleasePromotionTest extends TestCase
{
    public function testStableVersionAndSchemaMatchTheAcceptedRelease(): void
    {
        $plugin = $this->source('eventflow.php');
        self::assertStringContainsString('Version: 1.3.0', $plugin);
        self::assertStringContainsString("define('EVENTFLOW_VERSION', '1.3.0');", $plugin);
        self::assertStringContainsString("define('EVENTFLOW_SCHEMA_VERSION', 15);", $plugin);
        self::assertStringContainsString('## [1.3.0] - 2026-08-26', $this->source('CHANGELOG.md'));
    }

    public function testReleaseRecordsStagingAcceptanceAndFailClosedCutover(): void
    {
        $release = $this->source('docs/11-releases/1.3.0-sprint-12-production-readiness.md');

        foreach ([
            '**Status:** Stable source release; production cutover gated',
            '`v1.3.0-production-readiness`',
            'November 28, 2026, 5:00–7:00 PM',
            'Venice Banquet Hall',
            'admin@lui60.com',
            'one production email and one production SMS smoke test',
            'explicit launch and bulk-send authorization',
        ] as $expected) {
            self::assertStringContainsString($expected, $release);
        }

        self::assertStringContainsString('does not authorize production bulk communication', $this->source('README-IMP-102.md'));
    }

    private function source(string $path): string
    {
        $source = file_get_contents(dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $path));
        self::assertIsString($source, $path);
        return $source;
    }
}
