<?php

declare(strict_types=1);

use EventFlow\Application\Attendee\AttendeeRole;
use EventFlow\Application\Attendee\DesiredAttendee;
use EventFlow\Application\Attendee\InvitationResponseStatus;
use EventFlow\Application\Attendee\SubmitRsvp;
use EventFlow\Application\Authorization\PrincipalContext;
use EventFlow\Application\Import\ImportMapping;
use EventFlow\Application\Import\ImportStatus;
use EventFlow\Application\Persistence\EventScope;
use EventFlow\Bootstrap\Container;
use EventFlow\Infrastructure\Config\ConfigLoader;
use EventFlow\Infrastructure\Deployment\LocalBackupEvidenceVerifier;
use EventFlow\Infrastructure\Persistence\TableName;

$arguments = isset($args) && is_array($args) ? $args : array_slice($_SERVER['argv'] ?? [], 1);
$options = [];
foreach ($arguments as $argument) {
    if ($argument === '--confirm-reference-apply') $options['confirmed'] = true;
    elseif (is_string($argument) && preg_match('/^--([a-z0-9-]+)=(.*)$/', $argument, $matches) === 1) $options[$matches[1]] = $matches[2];
}
if (!defined('ABSPATH') || !isset($GLOBALS['wpdb']) || !is_object($GLOBALS['wpdb'])) { fwrite(STDERR, "Run this command inside WordPress with wp eval-file.\n"); exit(2); }
$version = $options['expected-version'] ?? null; $artifactSha = $options['artifact-sha256'] ?? null; $evidence = $options['backup-evidence'] ?? null; $sourcePath = $options['source'] ?? null; $sourceSha = $options['source-sha256'] ?? null; $eventId = filter_var($options['event-id'] ?? null, FILTER_VALIDATE_INT); $expected = filter_var($options['expected-invitations'] ?? null, FILTER_VALIDATE_INT);
if (!is_string($version) || !defined('EVENTFLOW_VERSION') || !hash_equals($version, (string) EVENTFLOW_VERSION) || !is_string($artifactSha) || preg_match('/^[a-f0-9]{64}$/', $artifactSha) !== 1
    || !is_string($evidence) || !is_string($sourcePath) || !is_string($sourceSha) || preg_match('/^[a-f0-9]{64}$/', $sourceSha) !== 1 || $eventId === false || $eventId < 1 || $expected === false || $expected < 1
    || !isset($options['confirmed']) || !defined('EVENTFLOW_ENV') || EVENTFLOW_ENV !== 'staging' || !defined('EVENTFLOW_PROTECTED_EXPORT_DIR')) {
    fwrite(STDERR, "Usage: wp eval-file tools/apply-lui60-reference-data.php -- --expected-version=VERSION --artifact-sha256=SHA256 --backup-evidence=/secure/evidence.json --source=/protected/reference.csv --source-sha256=SHA256 --event-id=ID --expected-invitations=137 --confirm-reference-apply\n"); exit(2);
}
try {
    (new LocalBackupEvidenceVerifier())->verify($evidence, $artifactSha, time());
    $protected = realpath((string) EVENTFLOW_PROTECTED_EXPORT_DIR); $sourceReal = realpath($sourcePath);
    if ($protected === false || $sourceReal === false || is_link($sourcePath) || !str_starts_with($sourceReal, rtrim($protected, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR)) throw new RuntimeException('reference_source_outside_protected_storage');
    $actualSha = hash_file('sha256', $sourceReal); if (!is_string($actualSha) || !hash_equals($sourceSha, $actualSha)) throw new RuntimeException('reference_source_hash_mismatch');
    $userId = function_exists('get_current_user_id') ? (int) get_current_user_id() : 0; if ($userId < 1) throw new RuntimeException('reference_wordpress_user_required');
    $container = Container::createFoundation((new ConfigLoader())->load(), $GLOBALS['wpdb']); $foundation = $container->database ?? throw new RuntimeException('reference_database_unavailable');
    $principal = PrincipalContext::wordpressUser($userId); $scope = new EventScope($eventId); $key = substr($sourceSha, 0, 24);
    $stage = $foundation->imports->stage($principal, $scope, $sourceReal, 'reference-stage-' . $key, basename($sourceReal)); $jobId = $stage->reference->entityId;
    $job = $foundation->database->fetchRow('SELECT import_status,total_rows,invalid_rows,failed_rows FROM ' . $foundation->tableNames->get(TableName::IMPORT_JOBS) . ' WHERE event_id=%d AND import_job_id=%d LIMIT 1', [$eventId, $jobId]) ?? throw new RuntimeException('reference_import_job_missing');
    if ((int) $job['total_rows'] !== $expected) throw new RuntimeException('reference_invitation_count_mismatch');
    if ($job['import_status'] === ImportStatus::STAGED->value) {
        $foundation->imports->validate($principal, $scope, $jobId, new ImportMapping(['primary_name' => 'primary_name', 'primary_email' => 'primary_email', 'primary_phone' => 'primary_phone', 'capacity' => 'capacity']), 'reference-validate-' . $key);
    }
    do {
        $job = $foundation->database->fetchRow('SELECT import_status,invalid_rows,failed_rows FROM ' . $foundation->tableNames->get(TableName::IMPORT_JOBS) . ' WHERE event_id=%d AND import_job_id=%d LIMIT 1', [$eventId, $jobId]) ?? throw new RuntimeException('reference_import_job_missing');
        if ((int) $job['invalid_rows'] !== 0 || (int) $job['failed_rows'] !== 0) throw new RuntimeException('reference_import_rows_invalid');
        if ($job['import_status'] === ImportStatus::COMPLETED->value) break;
        if (!in_array($job['import_status'], [ImportStatus::VALIDATED->value, ImportStatus::APPLYING->value], true)) throw new RuntimeException('reference_import_transition_invalid');
        $foundation->imports->applyBatch($principal, $scope, $jobId, 'reference-cli-' . $key, 100);
    } while (true);
    $importRows = $foundation->database->fetchAll('SELECT import_row_id,raw_data,applied_invitation_id FROM ' . $foundation->tableNames->get(TableName::IMPORT_ROWS) . ' WHERE event_id=%d AND import_job_id=%d AND row_status=%s ORDER BY source_row_number ASC', [$eventId, $jobId, 'applied']);
    $responsesApplied = 0;
    foreach ($importRows as $row) {
        $raw = json_decode((string) $row['raw_data'], true, 32, JSON_THROW_ON_ERROR);
        if (!is_array($raw) || ($raw['legacy_submitted'] ?? null) !== '1') continue;
        $invitationId = (int) $row['applied_invitation_id'];
        $invitation = $foundation->database->fetchRow('SELECT response_revision,response_status FROM ' . $foundation->tableNames->get(TableName::INVITATIONS) . ' WHERE event_id=%d AND invitation_id=%d LIMIT 1', [$eventId, $invitationId]) ?? throw new RuntimeException('reference_invitation_missing');
        if ($invitation['response_status'] === 'accepted') continue;
        if ($invitation['response_status'] !== 'pending') throw new RuntimeException('reference_response_conflict');
        $companionNames = json_decode((string) ($raw['legacy_companion_names'] ?? '[]'), true, 16, JSON_THROW_ON_ERROR); if (!is_array($companionNames)) throw new RuntimeException('reference_companions_invalid');
        $attendees = [new DesiredAttendee((string) $raw['primary_name'], AttendeeRole::PRIMARY, email: ($raw['primary_email'] ?? '') === '' ? null : (string) $raw['primary_email'], phone: ($raw['primary_phone'] ?? '') === '' ? null : (string) $raw['primary_phone'])];
        foreach ($companionNames as $name) { if (!is_string($name)) throw new RuntimeException('reference_companions_invalid'); $attendees[] = new DesiredAttendee($name, AttendeeRole::COMPANION); }
        $foundation->attendees->submitRsvp($principal, new SubmitRsvp($scope, $invitationId, (int) $invitation['response_revision'], InvitationResponseStatus::ACCEPTED, $attendees), 'reference-rsvp-' . $jobId . '-' . (int) $row['import_row_id']);
        $responsesApplied++;
    }
    fwrite(STDOUT, json_encode(['status' => 'pass', 'version' => EVENTFLOW_VERSION, 'event_id' => $eventId, 'import_job_id' => $jobId, 'imported_invitations' => count($importRows), 'responses_applied' => $responsesApplied, 'source_sha256' => $sourceSha], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) . PHP_EOL);
} catch (Throwable $exception) { fwrite(STDERR, "Reference apply failed: {$exception->getMessage()}\n"); exit(1); }
