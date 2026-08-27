<?php

namespace EventFlow\Tests\Integration;

use PHPUnit\Framework\TestCase;

final class Sprint12ProductionReadinessBaselineTest extends TestCase
{
    public function testSprintStartsFromStableUiReleaseWithoutSchemaChange(): void
    {
        $plugin = $this->source('eventflow.php');
        self::assertMatchesRegularExpression('/Version: 1\\.3\\.0(?:-dev)?/', $plugin);
        self::assertMatchesRegularExpression("/define\\('EVENTFLOW_VERSION', '1\\.3\\.0(?:-dev)?'\\);/", $plugin);
        self::assertStringContainsString("define('EVENTFLOW_SCHEMA_VERSION', 15);", $plugin);
        self::assertStringContainsString('`v1.2.0-ui-experience`', $this->source('README-IMP-092.md'));
    }

    public function testDeploymentPlanDefinesOrderedGatesAndPackageBoundaries(): void
    {
        $plan = $this->source('docs/09-developer-guide/EF-DOC-010-EventFlow-Production-Readiness-Plan-v1.0.md');
        foreach (['backup and rollback', 'schema version 15', '137 Invitations', 'provider and worker certification', 'assistive-technology', 'IMP-092', 'IMP-102'] as $expected) {
            self::assertStringContainsString($expected, $plan);
        }
    }

    public function testPreflightUsesPublicBoundedStatusEndpointsWithSecureTransportDefaults(): void
    {
        $service = $this->source('src/Application/Deployment/DeploymentPreflightService.php');
        foreach (['/system/health', '/system/readiness', 'secure_deployment_target_required', 'optional_capability', 'request_correlation'] as $expected) {
            self::assertStringContainsString($expected, $service);
        }
        $client = $this->source('src/Infrastructure/Deployment/CurlDeploymentStatusClient.php');
        foreach (['CURLOPT_FOLLOWLOCATION => false', 'CURLOPT_SSL_VERIFYPEER => true', 'CURLOPT_SSL_VERIFYHOST => 2', 'maximumBytes'] as $expected) {
            self::assertStringContainsString($expected, $client);
        }
        foreach (['Authorization:', 'Cookie:', 'CURLOPT_USERPWD'] as $forbidden) {
            self::assertStringNotContainsString($forbidden, $client);
        }
    }

    public function testCliRequiresExplicitTargetAndExpectedReleaseWithoutSecrets(): void
    {
        $tool = $this->source('tools/deployment-preflight.php');
        foreach (['--url', '--expected-version', '--allow-http-localhost', '--json'] as $expected) {
            self::assertStringContainsString($expected, $tool);
        }
        foreach (['password', 'api_key', 'authorization'] as $forbidden) {
            self::assertStringNotContainsString($forbidden, strtolower($tool));
        }
    }

    private function source(string $path): string
    {
        $source = file_get_contents(dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $path));
        self::assertIsString($source, $path);
        return $source;
    }
}
