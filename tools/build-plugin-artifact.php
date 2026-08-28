<?php

declare(strict_types=1);

use EventFlow\Application\Deployment\PluginArtifactBuilder;
use EventFlow\Infrastructure\Deployment\DependencyFreeProductionAutoloadGenerator;
use EventFlow\Infrastructure\Deployment\DeterministicZipWriter;

$root = dirname(__DIR__);
$autoload = $root . '/vendor/autoload.php';
if (!is_file($autoload)) {
    fwrite(STDERR, "Missing vendor/autoload.php; run composer install first.\n");
    exit(2);
}
require $autoload;

$options = getopt('', ['output:', 'source-date-epoch:', 'verify-reproducible', 'help']);
if (isset($options['help'])) {
    fwrite(STDOUT, "Usage: php tools/build-plugin-artifact.php [--output=dist] [--source-date-epoch=UNIX_TIME] [--verify-reproducible]\n");
    exit(0);
}

/** @return list<string> */
function eventflowGit(string $root, string $arguments): array
{
    $output = [];
    $exitCode = 0;
    exec('git -C ' . escapeshellarg($root) . ' ' . $arguments, $output, $exitCode);
    if ($exitCode !== 0) {
        throw new RuntimeException('artifact_git_metadata_unavailable');
    }
    return $output;
}

function eventflowRemoveBuildDirectory(string $directory): void
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

try {
    if (eventflowGit($root, 'status --porcelain --untracked-files=normal') !== []) {
        throw new RuntimeException('artifact_source_tree_must_be_clean');
    }
    $commit = trim(implode("\n", eventflowGit($root, 'rev-parse HEAD')));
    $commitEpoch = (int) trim(implode("\n", eventflowGit($root, 'show -s --format=%ct HEAD')));
    $plugin = (string) file_get_contents($root . '/eventflow.php');
    if (preg_match('/^ \* Version: ([0-9]+\.[0-9]+\.[0-9]+(?:-[a-z0-9.-]+)?)\r?$/m', $plugin, $matches) !== 1) {
        throw new RuntimeException('artifact_plugin_version_unavailable');
    }
    $version = $matches[1];
    $epochOption = $options['source-date-epoch'] ?? null;
    if ($epochOption !== null && (!is_string($epochOption) || preg_match('/^[0-9]{9,10}$/', $epochOption) !== 1)) {
        throw new RuntimeException('artifact_source_date_epoch_invalid');
    }
    $epoch = is_string($epochOption) ? (int) $epochOption : $commitEpoch;
    $outputOption = $options['output'] ?? 'dist';
    if (!is_string($outputOption) || trim($outputOption) === '') {
        throw new RuntimeException('artifact_output_invalid');
    }
    $output = str_starts_with($outputOption, '/') || preg_match('/^[A-Za-z]:[\\\\\/]/', $outputOption) === 1
        ? $outputOption
        : $root . '/' . $outputOption;
    $builder = new PluginArtifactBuilder(new DependencyFreeProductionAutoloadGenerator(), new DeterministicZipWriter());
    $result = $builder->build($root, $output, $version, $commit, $epoch);
    if (isset($options['verify-reproducible'])) {
        $secondOutput = rtrim($output, '/\\') . '/.reproducibility-check';
        try {
            $second = $builder->build($root, $secondOutput, $version, $commit, $epoch);
            if (!hash_equals($result->sha256, $second->sha256)) {
                throw new RuntimeException('artifact_reproducibility_failed');
            }
        } finally {
            eventflowRemoveBuildDirectory($secondOutput);
        }
    }
    fwrite(STDOUT, sprintf("Artifact PASS\nArchive: %s\nManifest: %s\nSHA-256: %s\nBytes: %d\nFiles: %d\n", $result->archivePath, $result->manifestPath, $result->sha256, $result->sizeBytes, $result->fileCount));
} catch (Throwable $exception) {
    fwrite(STDERR, "Artifact build failed: {$exception->getMessage()}\n");
    exit(1);
}
