<?php

namespace EventFlow\Tests\Integration;

use PHPUnit\Framework\TestCase;

final class Sprint12StagingEnvironmentValidationTest extends TestCase
{
    public function testEnvironmentGateCoversAllProductionLikePrerequisites(): void
    {
        $service = $this->source('src/Application/Deployment/StagingEnvironmentAcceptanceService.php');
        foreach ([
            'staging_environment_required', 'debug_mode_forbidden', 'unsupported_php_version',
            'unsupported_wordpress_version', 'unsupported_database_version', 'database_utf8mb4_required',
            'database_innodb_required', 'verified_https_required', 'cron_execution_not_configured',
            'protected_storage_not_ready', 'external_secret_injection_not_attested',
            'admin_hooks_not_registered', 'guest_shortcode_not_registered', 'rest_route_family_missing',
        ] as $expected) {
            self::assertStringContainsString($expected, $service);
        }
    }

    public function testProbeObservesWordPressCompositionWithoutExposingSensitiveValues(): void
    {
        $probe = $this->source('src/Infrastructure/Deployment/WordPressStagingEnvironmentProbe.php');
        foreach (['rest_get_server', 'shortcode_exists', 'registerMenu', 'enqueueAssets', 'EVENTFLOW_PROTECTED_EXPORT_DIR', 'EVENTFLOW_SECRETS_EXTERNAL'] as $expected) {
            self::assertStringContainsString($expected, $probe);
        }
        foreach (['DB_PASSWORD', 'AUTH_KEY', 'SECURE_AUTH_KEY', '$_SERVER[\'HTTP_AUTHORIZATION\']'] as $forbidden) {
            self::assertStringNotContainsString($forbidden, $probe);
        }
    }

    public function testWpCliSurfaceRequiresExactVersionAndReturnsNonzeroWhenBlocked(): void
    {
        $tool = $this->source('tools/staging-environment-acceptance.php');
        self::assertStringContainsString('--expected-version=', $tool);
        self::assertStringContainsString('--json', $tool);
        self::assertStringContainsString('exit($report->passed() ? 0 : 1)', $tool);
        self::assertStringContainsString("defined('ABSPATH')", $tool);
    }

    public function testDocumentationDoesNotFabricateLiveStagingAcceptance(): void
    {
        $report = $this->source('docs/10-testing/Sprint-12-Staging-Environment-Acceptance-Report.md');
        self::assertStringContainsString('BLOCKED', $report);
        self::assertStringContainsString('authorized-host execution has not been recorded', $report);
        self::assertStringNotContainsString('Live staging result: PASS', $report);
        self::assertStringContainsString('IMP-094', $this->source('CHANGELOG.md'));
    }

    private function source(string $path): string
    {
        $source = file_get_contents(dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $path));
        self::assertIsString($source, $path);
        return $source;
    }
}
