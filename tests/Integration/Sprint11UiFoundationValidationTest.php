<?php

namespace EventFlow\Tests\Integration;

use PHPUnit\Framework\TestCase;

final class Sprint11UiFoundationValidationTest extends TestCase
{
    public function testMetadataAdvancesFromReleasedApiBaselineWithoutSchemaChange(): void
    {
        $plugin = $this->source('eventflow.php');
        self::assertMatchesRegularExpression('/Version: (?:1\\.2\\.0|1\\.[3-9]\\.[0-9]+(?:-dev)?)/', $plugin);
        self::assertMatchesRegularExpression("/define\\('EVENTFLOW_VERSION', '(?:1\\.2\\.0|1\\.[3-9]\\.[0-9]+(?:-dev)?)'\\);/", $plugin);
        self::assertStringContainsString("define('EVENTFLOW_SCHEMA_VERSION', 15);", $plugin);
        self::assertStringContainsString('`v1.1.0-api-completion`', $this->source('README-IMP-081.md'));
    }

    public function testAdminFoundationPreservesApiAndOutputSecurityBoundaries(): void
    {
        $hooks = $this->source('src/Presentation/WordPress/WordPressAdminHooks.php');
        self::assertStringContainsString("wp_create_nonce('wp_rest')", $hooks);
        self::assertStringContainsString("'ready' => \$this->bootstrap->ready", $hooks);
        self::assertStringContainsString("hash_file('sha256', \$path)", $hooks);
        self::assertStringContainsString("assetVersion('assets/admin/eventflow-admin.css')", $hooks);
        self::assertStringContainsString("\$this->version . '-' . substr(\$digest, 0, 12)", $hooks);
        self::assertStringNotContainsString('global $wpdb', $hooks);

        $script = $this->source('assets/admin/eventflow-admin.js');
        self::assertStringContainsString("'X-WP-Nonce'", $script);
        self::assertStringContainsString('credentials:', $script);
        self::assertStringContainsString('textContent', $script);
        self::assertStringNotContainsString('innerHTML', $script);
        self::assertStringNotContainsString('insertAdjacentHTML', $script);
    }

    public function testUiBaselineDefinesAccessibilityStatesAndPackageSequence(): void
    {
        $design = $this->source('docs/07-ui-ux/EF-DOC-009-EventFlow-UI-UX-Design-v1.0.md');
        foreach (['WCAG 2.2 AA', 'loading, empty, success, stale, forbidden, unavailable, and retry', 'IMP-081', 'IMP-090'] as $expected) {
            self::assertStringContainsString($expected, $design);
        }
    }

    private function source(string $path): string
    {
        $source = file_get_contents(dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $path));
        self::assertIsString($source, $path);
        return $source;
    }
}
