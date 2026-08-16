<?php

namespace EventFlow\Tests\Integration;

use EventFlow\Bootstrap\RuntimeRequirements;
use EventFlow\Infrastructure\Persistence\Migration\CoreMigrationCatalogue;
use EventFlow\Infrastructure\Persistence\Migration\SqlMigrationLoader;
use EventFlow\Infrastructure\Persistence\TableName;
use PHPUnit\Framework\TestCase;

final class FoundationInvariantTest extends TestCase
{
    public function testMigrationChainEntryPointAndTableRegistryRemainAligned(): void
    {
        $root = dirname(__DIR__, 2);
        $definitions = (new CoreMigrationCatalogue(
            $root . '/database',
            new SqlMigrationLoader('wp_'),
        ))->definitions();

        $expectedFrom = 0;
        $sql = '';
        foreach ($definitions as $definition) {
            self::assertSame($expectedFrom, $definition->fromSchemaVersion);
            self::assertSame($expectedFrom + 1, $definition->toSchemaVersion);
            $expectedFrom = $definition->toSchemaVersion;
            $sql .= "\n" . implode("\n", $definition->statements);
        }

        $entryPoint = (string) file_get_contents($root . '/eventflow.php');
        self::assertMatchesRegularExpression(
            "/define\('EVENTFLOW_SCHEMA_VERSION', {$expectedFrom}\);/",
            $entryPoint,
        );
        self::assertStringContainsString(
            'Requires PHP: ' . RuntimeRequirements::MIN_PHP_VERSION,
            $entryPoint,
        );
        self::assertStringContainsString(
            'Requires at least: ' . RuntimeRequirements::MIN_WORDPRESS_VERSION,
            $entryPoint,
        );

        foreach (TableName::cases() as $table) {
            self::assertMatchesRegularExpression(
                '/CREATE TABLE(?: IF NOT EXISTS)?\s+wp_eventflow_' . preg_quote($table->value, '/') . '\s*\(/i',
                $sql,
                $table->value,
            );
        }
    }

    public function testLayerDirectionAndRuntimeSafetyRulesHold(): void
    {
        $root = dirname(__DIR__, 2) . '/src';
        foreach ($this->phpFiles($root . '/Application') as $file) {
            $source = (string) file_get_contents($file);
            self::assertStringNotContainsString('EventFlow\\Infrastructure\\', $source, $file);
            self::assertStringNotContainsString('EventFlow\\Presentation\\', $source, $file);
            self::assertStringNotContainsString('EventFlow\\Bootstrap\\Container', $source, $file);
            self::assertStringNotContainsString('$wpdb', $source, $file);
        }
        foreach ($this->phpFiles($root . '/Infrastructure') as $file) {
            self::assertStringNotContainsString(
                'EventFlow\\Presentation\\',
                (string) file_get_contents($file),
                $file,
            );
        }
        foreach ($this->phpFiles($root . '/Presentation') as $file) {
            self::assertStringNotContainsString('$wpdb', (string) file_get_contents($file), $file);
        }
        foreach ($this->phpFiles($root) as $file) {
            $source = (string) file_get_contents($file);
            self::assertDoesNotMatchRegularExpression('/\b(?:un)?serialize\s*\(/i', $source, $file);
        }
    }

    public function testOrdinaryBootstrapCannotExecuteMigrations(): void
    {
        $source = (string) file_get_contents(dirname(__DIR__, 2) . '/src/Bootstrap/ApplicationBootstrap.php');

        self::assertStringNotContainsString('MigrationRunner', $source);
        self::assertDoesNotMatchRegularExpression('/->(?:initialize|markRunning|markCompleted|markFailed)\s*\(/', $source);
        self::assertStringContainsString('currentSchemaVersion()', $source);
    }

    /** @return list<string> */
    private function phpFiles(string $directory): array
    {
        $files = [];
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($directory, \FilesystemIterator::SKIP_DOTS),
        );
        foreach ($iterator as $file) {
            if ($file->isFile() && $file->getExtension() === 'php') {
                $files[] = $file->getPathname();
            }
        }
        sort($files, SORT_STRING);

        return $files;
    }
}
