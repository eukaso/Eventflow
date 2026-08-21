<?php

namespace EventFlow\Application\Deployment;

use FilesystemIterator;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use RuntimeException;

final readonly class PluginArtifactBuilder
{
    private const ROOT_FILES = ['eventflow.php', 'composer.json', 'composer.lock'];
    private const RUNTIME_DIRECTORIES = ['src', 'assets/admin', 'assets/guest', 'database/migrations'];
    private const RUNTIME_FILES = ['database/eventflow-schema-baseline-v1.0.sql'];

    public function __construct(
        private ProductionAutoloadGenerator $autoload,
        private ArtifactArchiveWriter $zip,
    ) {
    }

    public function build(
        string $sourceRoot,
        string $outputDirectory,
        string $version,
        string $sourceCommit,
        int $sourceDateEpoch,
    ): ArtifactBuildResult {
        $sourceRoot = rtrim(str_replace('\\', '/', $sourceRoot), '/');
        if (!is_dir($sourceRoot)
            || !preg_match('/^[0-9]+\.[0-9]+\.[0-9]+(?:-[a-z0-9.-]+)?$/', $version)
            || !preg_match('/^[a-f0-9]{40}$/', $sourceCommit)
            || $sourceDateEpoch < 315532800
        ) {
            throw new RuntimeException('artifact_build_input_invalid');
        }
        if ((!is_dir($outputDirectory) && !mkdir($outputDirectory, 0775, true) && !is_dir($outputDirectory)) || is_link($outputDirectory)) {
            throw new RuntimeException('artifact_output_unavailable');
        }
        $work = rtrim(str_replace('\\', '/', $outputDirectory), '/') . '/.eventflow-build-' . bin2hex(random_bytes(8));
        $package = $work . '/eventflow';
        if (!mkdir($package, 0775, true)) {
            throw new RuntimeException('artifact_staging_unavailable');
        }
        try {
            foreach (self::ROOT_FILES as $path) {
                $this->copyFile($sourceRoot, $package, $path);
            }
            foreach (self::RUNTIME_FILES as $path) {
                $this->copyFile($sourceRoot, $package, $path);
            }
            foreach (self::RUNTIME_DIRECTORIES as $directory) {
                $this->copyDirectory($sourceRoot, $package, $directory);
            }
            $this->autoload->generate($package);
            $payload = $this->files($package);
            $payloadManifest = [
                'format_version' => 1,
                'package' => 'eventflow',
                'version' => $version,
                'source_commit' => $sourceCommit,
                'source_date_epoch' => $sourceDateEpoch,
                'payload_files' => array_map(
                    static fn (string $path, string $contents): array => [
                        'path' => $path,
                        'bytes' => strlen($contents),
                        'sha256' => hash('sha256', $contents),
                    ],
                    array_keys($payload),
                    array_values($payload),
                ),
            ];
            $internalManifest = json_encode($payloadManifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) . PHP_EOL;
            $payload['artifact-manifest.json'] = $internalManifest;
            ksort($payload, SORT_STRING);
            $archiveFiles = [];
            foreach ($payload as $path => $contents) {
                $archiveFiles['eventflow/' . $path] = $contents;
            }
            $baseName = 'eventflow-' . $version;
            $archivePath = rtrim($outputDirectory, '/\\') . DIRECTORY_SEPARATOR . $baseName . '.zip';
            $manifestPath = rtrim($outputDirectory, '/\\') . DIRECTORY_SEPARATOR . $baseName . '.manifest.json';
            $this->zip->write($archivePath, $archiveFiles, $sourceDateEpoch);
            $sha256 = hash_file('sha256', $archivePath);
            $size = filesize($archivePath);
            if (!is_string($sha256) || $size === false) {
                throw new RuntimeException('artifact_integrity_unavailable');
            }
            $externalManifest = [
                'format_version' => 1,
                'archive' => basename($archivePath),
                'bytes' => $size,
                'sha256' => $sha256,
                'package' => 'eventflow',
                'version' => $version,
                'source_commit' => $sourceCommit,
                'source_date_epoch' => $sourceDateEpoch,
                'file_count' => count($archiveFiles),
            ];
            $encoded = json_encode($externalManifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) . PHP_EOL;
            if (file_put_contents($manifestPath, $encoded, LOCK_EX) === false) {
                throw new RuntimeException('artifact_manifest_write_failed');
            }
            return new ArtifactBuildResult($archivePath, $manifestPath, $sha256, $size, count($archiveFiles));
        } finally {
            $this->removeDirectory($work);
        }
    }

    private function copyDirectory(string $sourceRoot, string $package, string $directory): void
    {
        $root = $sourceRoot . '/' . $directory;
        if (!is_dir($root) || is_link($root)) {
            throw new RuntimeException('artifact_runtime_directory_missing');
        }
        $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS));
        foreach ($iterator as $file) {
            if (!$file->isFile() || $file->isLink()) {
                throw new RuntimeException('artifact_symlink_or_special_file_forbidden');
            }
            $pathname = str_replace('\\', '/', $file->getPathname());
            $relative = $directory . '/' . substr($pathname, strlen($root) + 1);
            $this->copyFile($sourceRoot, $package, $relative);
        }
    }

    private function copyFile(string $sourceRoot, string $package, string $relative): void
    {
        $source = $sourceRoot . '/' . $relative;
        if (!is_file($source) || is_link($source)) {
            throw new RuntimeException('artifact_runtime_file_missing');
        }
        $target = $package . '/' . $relative;
        $directory = dirname($target);
        if (!is_dir($directory) && !mkdir($directory, 0775, true) && !is_dir($directory)) {
            throw new RuntimeException('artifact_staging_unavailable');
        }
        if (!copy($source, $target)) {
            throw new RuntimeException('artifact_staging_copy_failed');
        }
    }

    /** @return array<string,string> */
    private function files(string $package): array
    {
        $files = [];
        $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($package, FilesystemIterator::SKIP_DOTS));
        foreach ($iterator as $file) {
            if (!$file->isFile() || $file->isLink()) {
                throw new RuntimeException('artifact_symlink_or_special_file_forbidden');
            }
            $pathname = str_replace('\\', '/', $file->getPathname());
            $path = substr($pathname, strlen($package) + 1);
            if ($this->forbidden($path)) {
                throw new RuntimeException('artifact_forbidden_file_detected');
            }
            $contents = file_get_contents($file->getPathname());
            if ($contents === false) {
                throw new RuntimeException('artifact_file_unreadable');
            }
            $files[$path] = $this->normalize($path, $contents);
        }
        ksort($files, SORT_STRING);
        return $files;
    }

    private function forbidden(string $path): bool
    {
        $normalized = '/' . strtolower($path) . '/';
        foreach (['/.git/', '/tests/', '/docs/', '/node_modules/', '/build/', '/dist/', '/.tmp/'] as $segment) {
            if (str_contains($normalized, $segment)) {
                return true;
            }
        }
        return preg_match('#(?:^|/)(?:\.env(?:\..*)?|wp-config\.php|phpunit\.xml(?:\.dist)?|.*\.(?:log|tmp))$#i', $path) === 1;
    }

    private function normalize(string $path, string $contents): string
    {
        $extension = strtolower(pathinfo($path, PATHINFO_EXTENSION));
        if (in_array($extension, ['php', 'json', 'lock', 'js', 'css', 'sql'], true) && !str_contains($contents, "\0")) {
            return str_replace(["\r\n", "\r"], "\n", $contents);
        }
        return $contents;
    }

    private function removeDirectory(string $directory): void
    {
        if (!is_dir($directory)) {
            return;
        }
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($directory, FilesystemIterator::SKIP_DOTS),
            RecursiveIteratorIterator::CHILD_FIRST,
        );
        foreach ($iterator as $item) {
            $item->isDir() && !$item->isLink() ? rmdir($item->getPathname()) : unlink($item->getPathname());
        }
        rmdir($directory);
    }
}
