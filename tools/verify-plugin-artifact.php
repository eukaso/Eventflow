<?php

declare(strict_types=1);

use EventFlow\Infrastructure\Deployment\StoredZipReader;

$root = dirname(__DIR__);
$autoload = $root . '/vendor/autoload.php';
if (!is_file($autoload)) {
    fwrite(STDERR, "Missing vendor/autoload.php; run composer install first.\n");
    exit(2);
}
require $autoload;

$options = getopt('', ['archive:', 'manifest:', 'directory:', 'help']);
if (isset($options['help'])) {
    fwrite(STDOUT, "Usage: php tools/verify-plugin-artifact.php --directory=dist\n       php tools/verify-plugin-artifact.php --archive=eventflow.zip --manifest=eventflow.manifest.json\n");
    exit(0);
}
$archive = is_string($options['archive'] ?? null) ? $options['archive'] : '';
$manifestPath = is_string($options['manifest'] ?? null) ? $options['manifest'] : '';
try {
    $directory = $options['directory'] ?? null;
    if ($directory !== null) {
        if (!is_string($directory) || !is_dir($directory) || $archive !== '' || $manifestPath !== '') {
            throw new RuntimeException('artifact_verification_input_invalid');
        }
        $archives = glob(rtrim($directory, '/\\') . DIRECTORY_SEPARATOR . 'eventflow-*.zip') ?: [];
        $manifests = glob(rtrim($directory, '/\\') . DIRECTORY_SEPARATOR . 'eventflow-*.manifest.json') ?: [];
        if (count($archives) !== 1 || count($manifests) !== 1) {
            throw new RuntimeException('artifact_verification_input_invalid');
        }
        [$archive] = $archives;
        [$manifestPath] = $manifests;
    }
    if (!is_file($archive) || !is_file($manifestPath)) {
        throw new RuntimeException('artifact_verification_input_invalid');
    }
    $manifest = json_decode((string) file_get_contents($manifestPath), true, 32, JSON_THROW_ON_ERROR);
    if (!is_array($manifest)
        || ($manifest['archive'] ?? null) !== basename($archive)
        || ($manifest['bytes'] ?? null) !== filesize($archive)
        || !is_string($manifest['sha256'] ?? null)
        || !hash_equals($manifest['sha256'], (string) hash_file('sha256', $archive))
    ) {
        throw new RuntimeException('artifact_external_manifest_mismatch');
    }
    $files = (new StoredZipReader())->read($archive);
    foreach (['eventflow/eventflow.php', 'eventflow/vendor/autoload.php', 'eventflow/artifact-manifest.json'] as $required) {
        if (!isset($files[$required])) {
            throw new RuntimeException('artifact_required_file_missing');
        }
    }
    $internal = json_decode($files['eventflow/artifact-manifest.json'], true, 32, JSON_THROW_ON_ERROR);
    if (!is_array($internal)
        || ($internal['version'] ?? null) !== ($manifest['version'] ?? null)
        || ($internal['source_commit'] ?? null) !== ($manifest['source_commit'] ?? null)
        || ($manifest['file_count'] ?? null) !== count($files)
        || !is_array($internal['payload_files'] ?? null)
    ) {
        throw new RuntimeException('artifact_internal_manifest_mismatch');
    }
    $expectedPaths = ['eventflow/artifact-manifest.json' => true];
    foreach ($internal['payload_files'] as $record) {
        $path = is_array($record) && is_string($record['path'] ?? null) ? 'eventflow/' . $record['path'] : '';
        if ($path === '' || !isset($files[$path])
            || ($record['bytes'] ?? null) !== strlen($files[$path])
            || !is_string($record['sha256'] ?? null)
            || !hash_equals($record['sha256'], hash('sha256', $files[$path]))
        ) {
            throw new RuntimeException('artifact_payload_manifest_mismatch');
        }
        $expectedPaths[$path] = true;
    }
    if (array_diff_key($files, $expectedPaths) !== [] || array_diff_key($expectedPaths, $files) !== []) {
        throw new RuntimeException('artifact_payload_set_mismatch');
    }
    fwrite(STDOUT, sprintf("Artifact verification PASS\nSHA-256: %s\nFiles: %d\n", $manifest['sha256'], count($files)));
} catch (Throwable $exception) {
    fwrite(STDERR, "Artifact verification failed: {$exception->getMessage()}\n");
    exit(1);
}
