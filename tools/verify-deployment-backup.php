<?php

declare(strict_types=1);

use EventFlow\Infrastructure\Deployment\LocalBackupEvidenceVerifier;

$root = dirname(__DIR__);
$autoload = $root . '/vendor/autoload.php';
if (!is_file($autoload)) {
    fwrite(STDERR, "Missing vendor/autoload.php; run composer install first.\n");
    exit(2);
}
require $autoload;

$options = getopt('', ['evidence:', 'artifact-sha256:', 'help']);
if (isset($options['help'])) {
    fwrite(STDOUT, "Usage: php tools/verify-deployment-backup.php --evidence=/secure/evidence.json --artifact-sha256=SHA256\n");
    exit(0);
}
$evidencePath = is_string($options['evidence'] ?? null) ? $options['evidence'] : '';
$artifactSha = is_string($options['artifact-sha256'] ?? null) ? $options['artifact-sha256'] : '';
try {
    $verified = (new LocalBackupEvidenceVerifier())->verify($evidencePath, $artifactSha, time());
    fwrite(STDOUT, json_encode([
        'status' => 'pass',
        'evidence_id' => $verified->evidenceId,
        'evidence_sha256' => $verified->evidenceSha256,
        'target_environment' => $verified->targetEnvironment,
        'created_at' => $verified->createdAt,
        'database_sha256' => $verified->databaseSha256,
        'files_sha256' => $verified->filesSha256,
        'restore_procedure_id' => $verified->restoreProcedureId,
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) . PHP_EOL);
} catch (Throwable $exception) {
    fwrite(STDERR, "Backup verification failed: {$exception->getMessage()}\n");
    exit(1);
}
