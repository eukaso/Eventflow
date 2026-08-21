<?php

declare(strict_types=1);

use EventFlow\Application\Migration\MigrationPreflight;
use EventFlow\Application\Migration\MigrationRunner;
use EventFlow\Infrastructure\Deployment\LocalBackupEvidenceVerifier;
use EventFlow\Infrastructure\Deployment\WpdbDeploymentSchemaVerifier;
use EventFlow\Infrastructure\Persistence\Migration\CoreMigrationCatalogue;
use EventFlow\Infrastructure\Persistence\Migration\SqlMigrationLoader;
use EventFlow\Infrastructure\Persistence\WordPress\WpdbAdapter;
use EventFlow\Infrastructure\Persistence\WordPress\WpdbMigrationExecutor;
use EventFlow\Infrastructure\Persistence\WordPress\WpdbMigrationLock;
use EventFlow\Infrastructure\Persistence\WordPress\WpdbSchemaMetadataRepository;
use EventFlow\Infrastructure\Persistence\WordPress\WpdbTableNames;

$arguments = isset($args) && is_array($args) ? $args : array_slice($_SERVER['argv'] ?? [], 1);
$options = [];
foreach ($arguments as $argument) {
    if ($argument === '--confirm-fresh-install') {
        $options['fresh'] = true;
    } elseif (is_string($argument) && preg_match('/^--([a-z0-9-]+)=(.*)$/', $argument, $matches) === 1) {
        $options[$matches[1]] = $matches[2];
    }
}
if (!defined('ABSPATH') || !isset($GLOBALS['wpdb']) || !is_object($GLOBALS['wpdb'])) {
    fwrite(STDERR, "Run this command inside WordPress with wp eval-file.\n");
    exit(2);
}
$expectedVersion = $options['expected-version'] ?? null;
$artifactSha = $options['artifact-sha256'] ?? null;
$evidencePath = $options['backup-evidence'] ?? null;
if (!is_string($expectedVersion)
    || !defined('EVENTFLOW_VERSION')
    || !hash_equals($expectedVersion, (string) EVENTFLOW_VERSION)
    || !is_string($artifactSha)
    || preg_match('/^[a-f0-9]{64}$/', $artifactSha) !== 1
    || !is_string($evidencePath)
    || !isset($options['fresh'])
) {
    fwrite(STDERR, "Usage: wp eval-file tools/run-deployment-migrations.php -- --expected-version=1.3.0-dev --artifact-sha256=SHA256 --backup-evidence=/secure/evidence.json --confirm-fresh-install\n");
    exit(2);
}

try {
    $database = new WpdbAdapter($GLOBALS['wpdb']);
    $tables = new WpdbTableNames($database->tablePrefix());
    $repository = new WpdbSchemaMetadataRepository($database, $tables);
    $schema = new WpdbDeploymentSchemaVerifier($database, $tables, $repository);
    $backup = (new LocalBackupEvidenceVerifier())->verify($evidencePath, $artifactSha, time());
    $schema->assertFreshInstall();
    $catalogue = new CoreMigrationCatalogue(
        (string) EVENTFLOW_PLUGIN_DIR . '/database',
        new SqlMigrationLoader($database->tablePrefix()),
    );
    $definitions = $catalogue->definitions();
    $runner = new MigrationRunner(
        $repository,
        new WpdbMigrationLock($database),
        new WpdbMigrationExecutor($database),
        new MigrationPreflight(),
    );
    $applied = $runner->run($definitions, 'deployment');
    $verified = $schema->verify($definitions, (int) EVENTFLOW_SCHEMA_VERSION);
    $checksums = [];
    foreach ($definitions as $definition) {
        $checksums[] = ['key' => $definition->key, 'sha256' => $definition->checksum()];
    }
    fwrite(STDOUT, json_encode([
        'status' => 'pass',
        'version' => EVENTFLOW_VERSION,
        'schema_version' => $verified->schemaVersion,
        'migration_count' => $verified->migrationCount,
        'table_count' => $verified->tableCount,
        'applied_migrations' => $applied,
        'migration_checksums' => $checksums,
        'backup_evidence_id' => $backup->evidenceId,
        'backup_evidence_sha256' => $backup->evidenceSha256,
        'restore_procedure_id' => $backup->restoreProcedureId,
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) . PHP_EOL);
} catch (Throwable $exception) {
    fwrite(STDERR, "Deployment migration failed: {$exception->getMessage()}\n");
    exit(1);
}
