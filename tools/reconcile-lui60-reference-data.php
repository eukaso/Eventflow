<?php

declare(strict_types=1);

use EventFlow\Application\Deployment\ReferenceDataReconciliationService;
use EventFlow\Infrastructure\Deployment\WpdbLui60ReferenceData;
use EventFlow\Infrastructure\Persistence\WordPress\WpdbAdapter;
use EventFlow\Infrastructure\Persistence\WordPress\WpdbTableNames;

$arguments = isset($args) && is_array($args) ? $args : array_slice($_SERVER['argv'] ?? [], 1);
$options = [];
foreach ($arguments as $argument) if (is_string($argument) && preg_match('/^--([a-z0-9-]+)=(.*)$/', $argument, $matches) === 1) $options[$matches[1]] = $matches[2];
if (!defined('ABSPATH') || !isset($GLOBALS['wpdb']) || !is_object($GLOBALS['wpdb'])) { fwrite(STDERR, "Run this command inside WordPress with wp eval-file.\n"); exit(2); }
$version = $options['expected-version'] ?? null; $eventId = filter_var($options['event-id'] ?? null, FILTER_VALIDATE_INT); $jobId = filter_var($options['import-job-id'] ?? null, FILTER_VALIDATE_INT); $expected = filter_var($options['expected-invitations'] ?? null, FILTER_VALIDATE_INT);
if (!is_string($version) || !defined('EVENTFLOW_VERSION') || !hash_equals($version, (string) EVENTFLOW_VERSION) || $eventId === false || $eventId < 1 || $jobId === false || $jobId < 1 || $expected === false || $expected < 1 || !defined('EVENTFLOW_ENV') || EVENTFLOW_ENV !== 'staging') {
    fwrite(STDERR, "Usage: wp eval-file tools/reconcile-lui60-reference-data.php -- --expected-version=VERSION --event-id=ID --import-job-id=ID --expected-invitations=137\n"); exit(2);
}
try {
    $database = new WpdbAdapter($GLOBALS['wpdb']);
    $snapshot = (new WpdbLui60ReferenceData($database, new WpdbTableNames($database->tablePrefix())))->capture($eventId, $jobId);
    $report = (new ReferenceDataReconciliationService())->evaluate($snapshot, $expected);
    fwrite(STDOUT, json_encode($report->toArray(), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) . PHP_EOL);
    exit($report->passed() ? 0 : 1);
} catch (Throwable $exception) { fwrite(STDERR, "Reference reconciliation failed: {$exception->getMessage()}\n"); exit(1); }
