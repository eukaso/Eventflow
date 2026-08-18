<?php

namespace EventFlow\Tests\Integration;

use PHPUnit\Framework\TestCase;

final class Sprint9ReleaseCandidateTest extends TestCase
{
    public function testCandidatePreservesStableVersionUntilCiPromotion(): void
    {
        $plugin = $this->source('eventflow.php');
        self::assertStringContainsString('Version: 0.9.0', $plugin);
        self::assertStringContainsString("define('EVENTFLOW_VERSION', '0.9.0')", $plugin);
        self::assertStringContainsString("define('EVENTFLOW_SCHEMA_VERSION', 6)", $plugin);

        $release = $this->source('docs/11-releases/1.0.0-sprint-9-delivery-adapters.md');
        self::assertStringContainsString('**Status:** Release Candidate', $release);
        self::assertStringContainsString('`v1.0.0-delivery-adapters`', $release);
        self::assertStringContainsString('must wait for that gate', $release);

        $acceptance = $this->source('docs/10-testing/Sprint-09-Delivery-Adapters-Acceptance-Report.md');
        self::assertStringContainsString('LOCAL PASS / CI PENDING', $acceptance);
        self::assertStringContainsString('PHP 8.2/8.3 PENDING', $acceptance);
    }

    public function testCandidateLinksExecutableEvidenceAndControlledDeferrals(): void
    {
        $release = $this->source('docs/11-releases/1.0.0-sprint-9-delivery-adapters.md');
        foreach ([
            'catalogues/EventFlow-Delivery-Validation-Evidence-v1.0.csv',
            'catalogues/EventFlow-Delivery-Deferred-Routes-v1.0.csv',
            'docs/10-testing/Sprint-09-Delivery-Adapters-Acceptance-Report.md',
        ] as $path) {
            self::assertFileExists($this->root($path));
            self::assertStringContainsString($path, $release);
        }
        self::assertCount(15, $this->csv('catalogues/EventFlow-Delivery-Validation-Evidence-v1.0.csv'));
        self::assertCount(12, $this->csv('catalogues/EventFlow-Delivery-Deferred-Routes-v1.0.csv'));
    }

    /** @return list<array<string, string>> */
    private function csv(string $path): array
    {
        $handle = fopen($this->root($path), 'rb');
        self::assertIsResource($handle);
        $headers = fgetcsv($handle, escape: '');
        self::assertIsArray($headers);
        $rows = [];
        while (($values = fgetcsv($handle, escape: '')) !== false) {
            if ($values === [null]) continue;
            $row = array_combine($headers, $values);
            self::assertIsArray($row);
            $rows[] = $row;
        }
        fclose($handle);
        return $rows;
    }

    private function source(string $path): string
    {
        $source = file_get_contents($this->root($path));
        self::assertNotFalse($source, $path);
        return $source;
    }

    private function root(string $path): string
    {
        return dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $path);
    }
}
