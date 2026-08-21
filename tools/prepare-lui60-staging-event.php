<?php

declare(strict_types=1);

use EventFlow\Application\Attendee\AttendeeRole;
use EventFlow\Application\Attendee\DesiredAttendee;
use EventFlow\Application\Attendee\InvitationResponseStatus;
use EventFlow\Application\Attendee\SubmitRsvp;
use EventFlow\Application\Authorization\PrincipalContext;
use EventFlow\Application\Event\CreateEvent;
use EventFlow\Application\Import\ImportMapping;
use EventFlow\Application\Import\ImportStatus;
use EventFlow\Application\Invitation\CreateInvitation;
use EventFlow\Application\Persistence\EventScope;
use EventFlow\Bootstrap\Container;
use EventFlow\Infrastructure\Config\ConfigLoader;
use EventFlow\Infrastructure\Persistence\TableName;

$arguments = isset($args) && is_array($args) ? $args : array_slice($_SERVER['argv'] ?? [], 1);
$options = [];
foreach ($arguments as $argument) {
    if ($argument === '--confirm-synthetic-workflow') $options['confirmed'] = true;
    elseif (is_string($argument) && preg_match('/^--([a-z0-9-]+)=(.*)$/', $argument, $matches) === 1) $options[$matches[1]] = $matches[2];
}
$expectedVersion = $options['expected-version'] ?? null;
if (!defined('ABSPATH') || !isset($GLOBALS['wpdb']) || !is_object($GLOBALS['wpdb'])
    || !is_string($expectedVersion) || !defined('EVENTFLOW_VERSION') || !hash_equals($expectedVersion, (string) EVENTFLOW_VERSION)
    || !defined('EVENTFLOW_ENV') || EVENTFLOW_ENV !== 'staging' || !defined('EVENTFLOW_PROTECTED_EXPORT_DIR')
    || !isset($options['confirmed'])) {
    fwrite(STDERR, "Usage: wp --user=ADMIN eval-file tools/prepare-lui60-staging-event.php -- --expected-version=VERSION --confirm-synthetic-workflow\n");
    exit(2);
}

try {
    $userId = function_exists('get_current_user_id') ? (int) get_current_user_id() : 0;
    if ($userId < 1) throw new RuntimeException('synthetic_wordpress_user_required');
    $container = Container::createFoundation((new ConfigLoader())->load(), $GLOBALS['wpdb']);
    $foundation = $container->database ?? throw new RuntimeException('synthetic_database_unavailable');
    $principal = PrincipalContext::wordpressUser($userId);

    $smoke = $foundation->eventLifecycle->create(
        $principal,
        new CreateEvent('EventFlow Sprint 12 Synthetic Smoke', 'eventflow-sprint-12-synthetic-smoke', 'America/Edmonton'),
        'sprint12-synthetic-event-v1',
    );
    $smokeId = $smoke->reference->entityId;
    $scope = new EventScope($smokeId);
    $invitation = $foundation->invitations->createImported(
        $principal,
        new CreateInvitation($scope, 'Synthetic Primary', 2, 'synthetic@example.test', '+15550000000'),
        'sprint12-synthetic-invitation-v1',
    );
    $invitationId = $invitation->reference->entityId;
    $invitationRow = $foundation->database->fetchRow(
        'SELECT response_revision,response_status FROM ' . $foundation->tableNames->get(TableName::INVITATIONS) . ' WHERE event_id=%d AND invitation_id=%d LIMIT 1',
        [$smokeId, $invitationId],
    ) ?? throw new RuntimeException('synthetic_invitation_missing');
    if ($invitationRow['response_status'] === 'pending') {
        $foundation->attendees->submitRsvp(
            $principal,
            new SubmitRsvp($scope, $invitationId, (int) $invitationRow['response_revision'], InvitationResponseStatus::ACCEPTED, [
                new DesiredAttendee('Synthetic Primary', AttendeeRole::PRIMARY, email: 'synthetic@example.test', phone: '+15550000000'),
                new DesiredAttendee('Synthetic Companion', AttendeeRole::COMPANION),
            ]),
            'sprint12-synthetic-rsvp-v1',
        );
    } elseif ($invitationRow['response_status'] !== 'accepted') {
        throw new RuntimeException('synthetic_rsvp_conflict');
    }

    $protected = realpath((string) EVENTFLOW_PROTECTED_EXPORT_DIR);
    if ($protected === false || !is_dir($protected) || !is_writable($protected)) throw new RuntimeException('synthetic_storage_unavailable');
    $csv = $protected . DIRECTORY_SEPARATOR . 'eventflow-sprint12-synthetic-import.csv';
    if (!file_exists($csv)) {
        $handle = fopen($csv, 'xb');
        if ($handle === false) throw new RuntimeException('synthetic_import_unavailable');
        try {
            if (!chmod($csv, 0600)
                || fputcsv($handle, ['primary_name', 'primary_email', 'primary_phone', 'capacity'], ',', '"', '') === false
                || fputcsv($handle, ['Synthetic Import', 'synthetic-import@example.test', '+15550000001', 1], ',', '"', '') === false
                || !fflush($handle)) throw new RuntimeException('synthetic_import_unavailable');
        } finally {
            fclose($handle);
        }
    }
    $stage = $foundation->imports->stage($principal, $scope, $csv, 'sprint12-synthetic-import-stage-v1', basename($csv));
    $jobId = $stage->reference->entityId;
    $job = $foundation->database->fetchRow(
        'SELECT import_status,invalid_rows,failed_rows FROM ' . $foundation->tableNames->get(TableName::IMPORT_JOBS) . ' WHERE event_id=%d AND import_job_id=%d LIMIT 1',
        [$smokeId, $jobId],
    ) ?? throw new RuntimeException('synthetic_import_missing');
    if ($job['import_status'] === ImportStatus::STAGED->value) {
        $foundation->imports->validate($principal, $scope, $jobId, new ImportMapping([
            'primary_name' => 'primary_name', 'primary_email' => 'primary_email', 'primary_phone' => 'primary_phone', 'capacity' => 'capacity',
        ]), 'sprint12-synthetic-import-validate-v1');
    }
    do {
        $job = $foundation->database->fetchRow(
            'SELECT import_status,invalid_rows,failed_rows FROM ' . $foundation->tableNames->get(TableName::IMPORT_JOBS) . ' WHERE event_id=%d AND import_job_id=%d LIMIT 1',
            [$smokeId, $jobId],
        ) ?? throw new RuntimeException('synthetic_import_missing');
        if ((int) $job['invalid_rows'] !== 0 || (int) $job['failed_rows'] !== 0) throw new RuntimeException('synthetic_import_failed');
        if ($job['import_status'] === ImportStatus::COMPLETED->value) break;
        if (!in_array($job['import_status'], [ImportStatus::VALIDATED->value, ImportStatus::APPLYING->value], true)) throw new RuntimeException('synthetic_import_transition_invalid');
        $foundation->imports->applyBatch($principal, $scope, $jobId, 'sprint12-synthetic-worker', 10);
    } while (true);

    $events = $foundation->tableNames->get(TableName::EVENTS);
    $event = $foundation->database->fetchRow('SELECT event_status FROM ' . $events . ' WHERE event_id=%d LIMIT 1', [$smokeId]) ?? throw new RuntimeException('synthetic_event_missing');
    if ($event['event_status'] === 'draft') $foundation->eventLifecycle->cancel($principal, $scope, 'sprint12-synthetic-cancel-v1');
    $event = $foundation->database->fetchRow('SELECT event_status FROM ' . $events . ' WHERE event_id=%d LIMIT 1', [$smokeId]) ?? throw new RuntimeException('synthetic_event_missing');
    if ($event['event_status'] !== 'cancelled') throw new RuntimeException('synthetic_rollback_failed');

    $reference = $foundation->eventLifecycle->create(
        $principal,
        new CreateEvent('Lui @ 60 Reference Reconciliation (Staging)', 'lui60-reference-reconciliation-staging', 'America/Edmonton'),
        'sprint12-reference-event-v1',
    );
    fwrite(STDOUT, json_encode([
        'status' => 'pass', 'version' => EVENTFLOW_VERSION, 'synthetic_event_id' => $smokeId,
        'synthetic_import_job_id' => $jobId, 'rollback_status' => 'cancelled',
        'reference_event_id' => $reference->reference->entityId,
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) . PHP_EOL);
} catch (Throwable $exception) {
    fwrite(STDERR, "Synthetic staging workflow failed: {$exception->getMessage()}\n");
    exit(1);
}
