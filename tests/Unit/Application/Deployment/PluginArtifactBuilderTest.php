<?php

namespace EventFlow\Tests\Unit\Application\Deployment;

use EventFlow\Application\Deployment\PluginArtifactBuilder;
use EventFlow\Infrastructure\Deployment\DependencyFreeProductionAutoloadGenerator;
use EventFlow\Infrastructure\Deployment\DeterministicZipWriter;
use EventFlow\Infrastructure\Deployment\StoredZipReader;
use PHPUnit\Framework\TestCase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use RuntimeException;

final class PluginArtifactBuilderTest extends TestCase
{
    private string $temporary;

    protected function setUp(): void
    {
        $this->temporary = sys_get_temp_dir() . '/eventflow-artifact-' . bin2hex(random_bytes(8));
        self::assertTrue(mkdir($this->temporary, 0775, true));
    }

    protected function tearDown(): void
    {
        if (!is_dir($this->temporary)) {
            return;
        }
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($this->temporary, \FilesystemIterator::SKIP_DOTS),
            RecursiveIteratorIterator::CHILD_FIRST,
        );
        foreach ($iterator as $item) {
            $item->isDir() && !$item->isLink() ? rmdir($item->getPathname()) : unlink($item->getPathname());
        }
        rmdir($this->temporary);
    }

    public function testArtifactIsReproducibleAllowlistedAndSelfDescribing(): void
    {
        $source = $this->fixture();
        $builder = new PluginArtifactBuilder(
            new DependencyFreeProductionAutoloadGenerator(),
            new DeterministicZipWriter(),
        );
        $commit = str_repeat('a', 40);
        $epoch = 1787270400;

        $first = $builder->build($source, $this->temporary . '/first', '1.3.0-dev', $commit, $epoch);
        $second = $builder->build($source, $this->temporary . '/second', '1.3.0-dev', $commit, $epoch);

        self::assertSame($first->sha256, $second->sha256);
        self::assertSame(file_get_contents($first->archivePath), file_get_contents($second->archivePath));
        self::assertSame(hash_file('sha256', $first->archivePath), $first->sha256);

        $files = (new StoredZipReader())->read($first->archivePath);
        foreach ([
            'eventflow/eventflow.php',
            'eventflow/composer.json',
            'eventflow/composer.lock',
            'eventflow/src/ArtifactFixture/Probe.php',
            'eventflow/assets/admin/app.js',
            'eventflow/assets/guest/app.css',
            'eventflow/database/migrations/001_fixture.sql',
            'eventflow/database/eventflow-schema-baseline-v1.0.sql',
            'eventflow/vendor/autoload.php',
            'eventflow/artifact-manifest.json',
        ] as $required) {
            self::assertArrayHasKey($required, $files);
        }
        foreach (['eventflow/tests/SecretTest.php', 'eventflow/docs/internal.md', 'eventflow/.env'] as $forbidden) {
            self::assertArrayNotHasKey($forbidden, $files);
        }
        self::assertStringNotContainsString("\r", $files['eventflow/assets/admin/app.js']);

        $internal = json_decode($files['eventflow/artifact-manifest.json'], true, 32, JSON_THROW_ON_ERROR);
        $external = json_decode((string) file_get_contents($first->manifestPath), true, 32, JSON_THROW_ON_ERROR);
        self::assertSame($commit, $internal['source_commit']);
        self::assertSame($epoch, $internal['source_date_epoch']);
        self::assertSame($first->sha256, $external['sha256']);
        self::assertSame(filesize($first->archivePath), $external['bytes']);
        self::assertSame(count($files), $external['file_count']);
    }

    public function testArchiveWriterRejectsTraversalPaths(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('artifact_file_invalid');
        (new DeterministicZipWriter())->write(
            $this->temporary . '/unsafe.zip',
            ['eventflow/../secret.php' => 'secret'],
            1787270400,
        );
    }

    public function testAutoloadGenerationFailsClosedForNewRuntimeDependencies(): void
    {
        $package = $this->temporary . '/package';
        self::assertTrue(mkdir($package, 0775, true));
        file_put_contents($package . '/composer.json', json_encode([
            'autoload' => ['psr-4' => ['EventFlow\\' => 'src/']],
            'require' => ['php' => '>=8.2', 'vendor/package' => '^1.0'],
        ], JSON_THROW_ON_ERROR));

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('artifact_runtime_dependency_requires_review');
        (new DependencyFreeProductionAutoloadGenerator())->generate($package);
    }

    private function fixture(): string
    {
        $root = $this->temporary . '/source';
        foreach (['src/ArtifactFixture', 'assets/admin', 'assets/guest', 'database/migrations', 'tests', 'docs'] as $directory) {
            self::assertTrue(mkdir($root . '/' . $directory, 0775, true));
        }
        file_put_contents($root . '/eventflow.php', "<?php\n/* Version: 1.3.0-dev */\n");
        file_put_contents($root . '/composer.json', json_encode([
            'autoload' => ['psr-4' => ['EventFlow\\' => 'src/']],
            'require' => ['php' => '>=8.2'],
        ], JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR));
        file_put_contents($root . '/composer.lock', "{\r\n  \"packages\": []\r\n}\r\n");
        file_put_contents($root . '/src/ArtifactFixture/Probe.php', "<?php\nnamespace EventFlow\\ArtifactFixture;\nfinal class Probe {}\n");
        file_put_contents($root . '/assets/admin/app.js', "const ready = true;\r\n");
        file_put_contents($root . '/assets/guest/app.css', "body { color: black; }\n");
        file_put_contents($root . '/database/migrations/001_fixture.sql', "SELECT 1;\n");
        file_put_contents($root . '/database/eventflow-schema-baseline-v1.0.sql', "SELECT 1;\n");
        file_put_contents($root . '/tests/SecretTest.php', "<?php // excluded\n");
        file_put_contents($root . '/docs/internal.md', "excluded\n");
        file_put_contents($root . '/.env', "SECRET=excluded\n");
        return $root;
    }
}
