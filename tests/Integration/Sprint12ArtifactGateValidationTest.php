<?php

namespace EventFlow\Tests\Integration;

use PHPUnit\Framework\TestCase;

final class Sprint12ArtifactGateValidationTest extends TestCase
{
    public function testBuilderUsesAnExplicitProductionRuntimeAllowlist(): void
    {
        $builder = $this->source('src/Application/Deployment/PluginArtifactBuilder.php');
        foreach (['eventflow.php', 'composer.json', 'composer.lock', 'src', 'assets/admin', 'assets/guest', 'database/migrations', 'eventflow-schema-baseline-v1.0.sql'] as $expected) {
            self::assertStringContainsString($expected, $builder);
        }
        foreach (['/.git/', '/tests/', '/docs/', '/node_modules/', '\\.env'] as $forbidden) {
            self::assertStringContainsString($forbidden, $builder);
        }
    }

    public function testArchiveHasDeterministicOrderingTimestampAndModesWithoutZipExtension(): void
    {
        $writer = $this->source('src/Infrastructure/Deployment/DeterministicZipWriter.php');
        foreach (['ksort($files, SORT_STRING)', "gmdate('Y'", '0100644 << 16', 'LOCK_EX'] as $expected) {
            self::assertStringContainsString($expected, $writer);
        }
        self::assertStringNotContainsString('ZipArchive', $writer);
    }

    public function testBuildAndVerificationToolsEnforceProvenanceAndIntegrity(): void
    {
        $build = $this->source('tools/build-plugin-artifact.php');
        foreach (['status --porcelain', 'rev-parse HEAD', '--verify-reproducible', 'artifact_reproducibility_failed'] as $expected) {
            self::assertStringContainsString($expected, $build);
        }
        self::assertStringContainsString('?)\\r?$/m', $build);
        $verify = $this->source('tools/verify-plugin-artifact.php');
        foreach (['hash_file', 'artifact_payload_manifest_mismatch', 'artifact_payload_set_mismatch', '--directory'] as $expected) {
            self::assertStringContainsString($expected, $verify);
        }
    }

    public function testRuntimeDependenciesFailClosedAndCiExecutesArtifactGate(): void
    {
        $autoload = $this->source('src/Infrastructure/Deployment/DependencyFreeProductionAutoloadGenerator.php');
        self::assertStringContainsString("\$requirements !== ['php']", $autoload);
        self::assertStringContainsString('artifact_runtime_dependency_requires_review', $autoload);
        $workflow = $this->source('.github/workflows/eventflow-tests.yml');
        self::assertStringContainsString('--verify-reproducible', $workflow);
        self::assertStringContainsString('verify-plugin-artifact.php --directory=build/artifacts', $workflow);
    }

    public function testArtifactBoundaryAndLimitationsAreDocumented(): void
    {
        $readme = $this->source('README-IMP-093.md');
        foreach (['clean committed source tree', 'internal manifest', 'external manifest', 'does not replace'] as $expected) {
            self::assertStringContainsString($expected, $readme);
        }
        self::assertStringContainsString('IMP-093', $this->source('CHANGELOG.md'));
    }

    private function source(string $path): string
    {
        $source = file_get_contents(dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $path));
        self::assertIsString($source, $path);
        return $source;
    }
}
