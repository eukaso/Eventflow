<?php

declare(strict_types=1);

use EventFlow\Infrastructure\Deployment\LocalBackupEvidenceVerifier;
use EventFlow\Infrastructure\Deployment\WpdbLui60ReferenceData;
use EventFlow\Infrastructure\Persistence\WordPress\WpdbAdapter;
use EventFlow\Infrastructure\Persistence\WordPress\WpdbTableNames;

$arguments = isset($args) && is_array($args) ? $args : array_slice($_SERVER['argv'] ?? [], 1);
$options = [];
foreach ($arguments as $argument) {
    if ($argument === '--confirm-protected-export') $options['confirmed'] = true;
    elseif (is_string($argument) && preg_match('/^--([a-z0-9-]+)=(.*)$/', $argument, $matches) === 1) $options[$matches[1]] = $matches[2];
}
if (!defined('ABSPATH') || !isset($GLOBALS['wpdb']) || !is_object($GLOBALS['wpdb'])) { fwrite(STDERR, "Run this command inside WordPress with wp eval-file.\n"); exit(2); }
$expectedVersion = $options['expected-version'] ?? null; $artifactSha = $options['artifact-sha256'] ?? null; $evidence = $options['backup-evidence'] ?? null; $output = $options['output'] ?? null; $expected = filter_var($options['expected-invitations'] ?? null, FILTER_VALIDATE_INT);
if (!is_string($expectedVersion) || !defined('EVENTFLOW_VERSION') || !hash_equals($expectedVersion, (string) EVENTFLOW_VERSION)
    || !is_string($artifactSha) || preg_match('/^[a-f0-9]{64}$/', $artifactSha) !== 1 || !is_string($evidence) || !is_string($output)
    || $expected === false || $expected < 1 || !isset($options['confirmed']) || !defined('EVENTFLOW_ENV') || EVENTFLOW_ENV !== 'staging'
    || !defined('EVENTFLOW_PROTECTED_EXPORT_DIR')) {
    fwrite(STDERR, "Usage: wp eval-file tools/export-lui60-reference-data.php -- --expected-version=VERSION --artifact-sha256=SHA256 --backup-evidence=/secure/evidence.json --output=/protected/reference.csv --expected-invitations=137 --confirm-protected-export\n"); exit(2);
}
try {
    (new LocalBackupEvidenceVerifier())->verify($evidence, $artifactSha, time());
    $protected = realpath((string) EVENTFLOW_PROTECTED_EXPORT_DIR); $parent = realpath(dirname($output));
    if ($protected === false || $parent === false || !hash_equals(rtrim($protected, DIRECTORY_SEPARATOR), rtrim($parent, DIRECTORY_SEPARATOR))) throw new RuntimeException('reference_export_outside_protected_storage');
    $database = new WpdbAdapter($GLOBALS['wpdb']);
    $result = (new WpdbLui60ReferenceData($database, new WpdbTableNames($database->tablePrefix())))->export($output);
    if ($result->invitations !== $expected) throw new RuntimeException('reference_invitation_count_mismatch');
    fwrite(STDOUT, json_encode(['status' => 'pass', 'version' => EVENTFLOW_VERSION, 'export' => $result->toArray()], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) . PHP_EOL);
} catch (Throwable $exception) { fwrite(STDERR, "Reference export failed: {$exception->getMessage()}\n"); exit(1); }
