<?php

namespace EventFlow\Infrastructure\Persistence\WordPress;

use DateTimeImmutable;
use DateTimeZone;
use EventFlow\Application\Persistence\EventScope;
use EventFlow\Application\Privacy\{PrivacyActionRecord, PrivacyException, PrivacyRepository, RetentionHoldRecord};
use EventFlow\Infrastructure\Persistence\{PersistenceException, TableName};

final class WpdbPrivacyRepository extends AbstractWpdbRepository implements PrivacyRepository
{
    public function createAction(EventScope $scope, int $invitationId, string $kind, string $policyVersion, string $purpose, ?int $actorUserId, DateTimeImmutable $now): PrivacyActionRecord
    {
        $this->assertSubjectExists($scope, $invitationId);
        $this->assertNoHold($scope, $invitationId);
        $table = $this->table(TableName::PRIVACY_ACTIONS);
        $existing = $this->database->fetchRow("SELECT * FROM {$table} WHERE event_id=%d AND invitation_id=%d AND request_kind=%s AND policy_version=%s LIMIT 1 FOR UPDATE", [$scope->eventId, $invitationId, $kind, $policyVersion]);
        if ($existing !== null) {
            return $this->action($existing, $scope);
        }
        $actorSql = $actorUserId === null ? 'NULL' : '%d';
        $parameters = [$scope->eventId, $invitationId, $kind, $policyVersion, $purpose];
        if ($actorUserId !== null) {
            $parameters[] = $actorUserId;
        }
        $timestamp = $this->time($now);
        array_push($parameters, $timestamp, $timestamp, $timestamp);
        if ($this->database->execute("INSERT INTO {$table} (event_id,invitation_id,request_kind,policy_version,purpose,action_status,checkpoint,requested_by_user_id,requested_at,created_at,updated_at) VALUES (%d,%d,%s,%s,%s,'pending','requested',{$actorSql},%s,%s,%s)", $parameters) !== 1) {
            throw new PersistenceException('privacy_action_create_failed');
        }
        return new PrivacyActionRecord($this->database->lastInsertId(), $scope, $invitationId, $kind, $policyVersion, $purpose, 'pending', 'requested');
    }

    public function resume(EventScope $scope, int $actionId, DateTimeImmutable $now): PrivacyActionRecord
    {
        $table = $this->table(TableName::PRIVACY_ACTIONS);
        $row = $this->database->fetchRow("SELECT * FROM {$table} WHERE event_id=%d AND privacy_action_id=%d LIMIT 1 FOR UPDATE", [$scope->eventId, $actionId]);
        if ($row === null) {
            throw new PrivacyException('resource_not_found');
        }
        $action = $this->action($row, $scope);
        if ($action->status === 'completed') {
            return $action;
        }
        $this->assertNoHold($scope, $action->invitationId);
        $this->database->execute("UPDATE {$table} SET action_status='processing',failure_code=NULL,updated_at=%s WHERE event_id=%d AND privacy_action_id=%d", [$this->time($now), $scope->eventId, $actionId]);
        return new PrivacyActionRecord($actionId, $scope, $action->invitationId, $action->requestKind, $action->policyVersion, $action->purpose, 'processing', $action->checkpoint);
    }

    public function advance(PrivacyActionRecord $action, string $checkpoint, DateTimeImmutable $now): PrivacyActionRecord
    {
        $table = $this->table(TableName::PRIVACY_ACTIONS);
        if ($this->database->execute("UPDATE {$table} SET action_status='processing',checkpoint=%s,failure_code=NULL,updated_at=%s WHERE event_id=%d AND privacy_action_id=%d AND checkpoint=%s", [$checkpoint, $this->time($now), $action->eventScope->eventId, $action->privacyActionId, $action->checkpoint]) !== 1) {
            throw new PrivacyException('resource_modified');
        }
        return new PrivacyActionRecord($action->privacyActionId, $action->eventScope, $action->invitationId, $action->requestKind, $action->policyVersion, $action->purpose, 'processing', $checkpoint);
    }

    public function fail(PrivacyActionRecord $action, string $failureCode, DateTimeImmutable $now): void
    {
        $table = $this->table(TableName::PRIVACY_ACTIONS);
        $this->database->execute("UPDATE {$table} SET action_status='failed',failure_code=%s,updated_at=%s WHERE event_id=%d AND privacy_action_id=%d AND action_status<>'completed'", [$failureCode, $this->time($now), $action->eventScope->eventId, $action->privacyActionId]);
    }

    public function revokeCredentials(PrivacyActionRecord $action, DateTimeImmutable $now): void
    {
        $eventId = $action->eventScope->eventId;
        $timestamp = $this->time($now);
        $invitations = $this->table(TableName::INVITATIONS);
        $sessions = $this->table(TableName::GUEST_SESSIONS);
        $links = $this->table(TableName::GUEST_LINK_CREDENTIALS);
        $this->database->execute("UPDATE {$invitations} SET token_revoked_at=COALESCE(token_revoked_at,%s),token_version=token_version+1,updated_at=%s WHERE event_id=%d AND invitation_id=%d", [$timestamp, $timestamp, $eventId, $action->invitationId]);
        $this->database->execute("UPDATE {$sessions} SET session_status='revoked',revoked_at=COALESCE(revoked_at,%s),updated_at=%s WHERE event_id=%d AND invitation_id=%d AND session_status='active'", [$timestamp, $timestamp, $eventId, $action->invitationId]);
        $this->database->execute("UPDATE {$links} SET credential_status='revoked',revoked_at=COALESCE(revoked_at,%s) WHERE event_id=%d AND invitation_id=%d AND credential_status='active'", [$timestamp, $eventId, $action->invitationId]);
    }

    public function minimizePii(PrivacyActionRecord $action, DateTimeImmutable $now): void
    {
        $eventId = $action->eventScope->eventId;
        $invitationId = $action->invitationId;
        $timestamp = $this->time($now);
        $invitations = $this->table(TableName::INVITATIONS);
        $attendees = $this->table(TableName::ATTENDEES);
        $messages = $this->table(TableName::MESSAGES);
        $attempts = $this->table(TableName::MESSAGE_DELIVERY_ATTEMPTS);
        $providerEvents = $this->table(TableName::PROVIDER_EVENTS);
        $importRows = $this->table(TableName::IMPORT_ROWS);
        $checkins = $this->table(TableName::CHECKINS);
        $seatingGroups = $this->table(TableName::SEATING_GROUPS);
        $this->database->execute("UPDATE {$invitations} SET invitation_code=CONCAT('anon-',invitation_id),primary_name='Anonymized Guest',primary_email=NULL,primary_email_normalized=NULL,primary_phone=NULL,primary_phone_normalized=NULL,organizer_notes=NULL,updated_at=%s WHERE event_id=%d AND invitation_id=%d", [$timestamp, $eventId, $invitationId]);
        $this->database->execute("UPDATE {$attendees} SET first_name=NULL,last_name=NULL,display_name='Anonymized Attendee',email=NULL,email_normalized=NULL,phone=NULL,phone_normalized=NULL,dietary_requirements=NULL,accessibility_requirements=NULL,organizer_notes=NULL,updated_at=%s WHERE event_id=%d AND invitation_id=%d", [$timestamp, $eventId, $invitationId]);
        $this->database->execute("UPDATE {$messages} SET recipient_name='Anonymized Recipient',recipient_address='redacted@invalid.local',recipient_address_normalized=NULL,subject=NULL,rendered_content='[content removed by privacy action]',plain_text_content=NULL,updated_at=%s WHERE event_id=%d AND invitation_id=%d", [$timestamp, $eventId, $invitationId]);
        $this->database->execute("UPDATE {$attempts} d JOIN {$messages} m ON m.event_id=d.event_id AND m.message_id=d.message_id SET d.error_message=NULL WHERE m.event_id=%d AND m.invitation_id=%d", [$eventId, $invitationId]);
        $this->database->execute("UPDATE {$providerEvents} p JOIN {$messages} m ON m.event_id=p.event_id AND m.message_id=p.message_id SET p.reason_message=NULL WHERE m.event_id=%d AND m.invitation_id=%d", [$eventId, $invitationId]);
        $this->database->execute("UPDATE {$importRows} SET raw_data=JSON_OBJECT(),normalized_data=NULL,validation_errors=NULL,validation_warnings=NULL,updated_at=%s WHERE event_id=%d AND (matched_invitation_id=%d OR applied_invitation_id=%d)", [$timestamp, $eventId, $invitationId, $invitationId]);
        $this->database->execute("UPDATE {$checkins} c JOIN {$attendees} a ON a.event_id=c.event_id AND a.attendee_id=c.attendee_id SET c.reason=NULL,c.notes=NULL WHERE a.event_id=%d AND a.invitation_id=%d", [$eventId, $invitationId]);
        $this->database->execute("UPDATE {$seatingGroups} SET group_name=CONCAT('Anonymized Group ',seating_group_id),updated_at=%s WHERE event_id=%d AND source_invitation_id=%d", [$timestamp, $eventId, $invitationId]);
    }

    public function invalidatePiiExports(PrivacyActionRecord $action, DateTimeImmutable $now): array
    {
        $table = $this->table(TableName::EXPORTS);
        $rows = $this->database->fetchAll("SELECT artifact_locator FROM {$table} WHERE event_id=%d AND contains_pii=1 AND export_status IN ('pending','generating','ready','invalidated') AND artifact_locator IS NOT NULL FOR UPDATE", [$action->eventScope->eventId]);
        $this->database->execute("UPDATE {$table} SET export_status='invalidated',updated_at=%s WHERE event_id=%d AND contains_pii=1 AND export_status IN ('pending','generating','ready')", [$this->time($now), $action->eventScope->eventId]);
        return $this->locators($rows);
    }

    public function invalidatedArtifactLocators(PrivacyActionRecord $action): array
    {
        $table = $this->table(TableName::EXPORTS);
        return $this->locators($this->database->fetchAll("SELECT artifact_locator FROM {$table} WHERE event_id=%d AND contains_pii=1 AND export_status='invalidated' AND artifact_locator IS NOT NULL", [$action->eventScope->eventId]));
    }

    public function recordTombstone(PrivacyActionRecord $action, DateTimeImmutable $now): void
    {
        $table = $this->table(TableName::PRIVACY_STATES);
        $timestamp = $this->time($now);
        $hash = hash('sha256', 'eventflow-privacy-v1:' . $action->eventScope->eventId . ':' . $action->invitationId);
        $this->database->execute("INSERT INTO {$table} (event_id,invitation_id,privacy_action_id,policy_version,subject_key_hash,privacy_state,reconciliation_status,anonymized_at,reconciled_at,created_at,updated_at) VALUES (%d,%d,%d,%s,%s,'anonymized','reconciled',%s,%s,%s,%s) ON DUPLICATE KEY UPDATE privacy_action_id=VALUES(privacy_action_id),policy_version=VALUES(policy_version),privacy_state='anonymized',reconciliation_status='reconciled',anonymized_at=VALUES(anonymized_at),reconciled_at=VALUES(reconciled_at),updated_at=VALUES(updated_at)", [$action->eventScope->eventId, $action->invitationId, $action->privacyActionId, $action->policyVersion, $hash, $timestamp, $timestamp, $timestamp, $timestamp]);
    }

    public function complete(PrivacyActionRecord $action, DateTimeImmutable $now): PrivacyActionRecord
    {
        $table = $this->table(TableName::PRIVACY_ACTIONS);
        $timestamp = $this->time($now);
        if ($this->database->execute("UPDATE {$table} SET action_status='completed',checkpoint='completed',completed_at=%s,failure_code=NULL,updated_at=%s WHERE event_id=%d AND privacy_action_id=%d AND checkpoint='tombstone_recorded'", [$timestamp, $timestamp, $action->eventScope->eventId, $action->privacyActionId]) !== 1) {
            throw new PrivacyException('resource_modified');
        }
        return new PrivacyActionRecord($action->privacyActionId, $action->eventScope, $action->invitationId, $action->requestKind, $action->policyVersion, $action->purpose, 'completed', 'completed');
    }

    public function placeHold(EventScope $scope, ?int $invitationId, string $policyVersion, string $reason, int $actorUserId, DateTimeImmutable $now): RetentionHoldRecord
    {
        if ($invitationId !== null) {
            $this->assertSubjectExists($scope, $invitationId);
        }
        $actions = $this->table(TableName::PRIVACY_ACTIONS);
        $scopeSql = $invitationId === null ? '' : ' AND invitation_id=%d';
        $scopeParameters = [$scope->eventId];
        if ($invitationId !== null) {
            $scopeParameters[] = $invitationId;
        }
        if ($this->database->fetchValue("SELECT privacy_action_id FROM {$actions} WHERE event_id=%d{$scopeSql} AND action_status<>'completed' LIMIT 1 FOR UPDATE", $scopeParameters) !== null) {
            throw new PrivacyException('privacy_action_in_progress');
        }
        $table = $this->table(TableName::RETENTION_HOLDS);
        $invitationSql = $invitationId === null ? 'NULL' : '%d';
        $parameters = [$scope->eventId];
        if ($invitationId !== null) {
            $parameters[] = $invitationId;
        }
        array_push($parameters, $policyVersion, $reason, $actorUserId, $this->time($now), $this->time($now), $this->time($now));
        $this->database->execute("INSERT INTO {$table} (event_id,invitation_id,policy_version,reason,hold_status,placed_by_user_id,placed_at,created_at,updated_at) VALUES (%d,{$invitationSql},%s,%s,'active',%d,%s,%s,%s)", $parameters);
        return new RetentionHoldRecord($this->database->lastInsertId(), $scope, $invitationId, $policyVersion, $reason, 'active');
    }

    public function releaseHold(EventScope $scope, int $holdId, int $actorUserId, DateTimeImmutable $now): RetentionHoldRecord
    {
        $table = $this->table(TableName::RETENTION_HOLDS);
        $row = $this->database->fetchRow("SELECT * FROM {$table} WHERE event_id=%d AND retention_hold_id=%d LIMIT 1 FOR UPDATE", [$scope->eventId, $holdId]);
        if ($row === null) {
            throw new PrivacyException('resource_not_found');
        }
        if ((string) $row['hold_status'] !== 'active') {
            throw new PrivacyException('retention_hold_not_active');
        }
        $timestamp = $this->time($now);
        $this->database->execute("UPDATE {$table} SET hold_status='released',released_by_user_id=%d,released_at=%s,updated_at=%s WHERE event_id=%d AND retention_hold_id=%d AND hold_status='active'", [$actorUserId, $timestamp, $timestamp, $scope->eventId, $holdId]);
        return new RetentionHoldRecord($holdId, $scope, $row['invitation_id'] === null ? null : (int) $row['invitation_id'], (string) $row['policy_version'], (string) $row['reason'], 'released');
    }

    public function isReconciled(): bool
    {
        try {
            $states = $this->table(TableName::PRIVACY_STATES);
            return (int) $this->database->fetchValue("SELECT COUNT(*) FROM {$states} WHERE reconciliation_status<>'reconciled'") === 0;
        } catch (PersistenceException) {
            return false;
        }
    }

    public function requireReconciliation(DateTimeImmutable $now): int
    {
        $table = $this->table(TableName::PRIVACY_STATES);
        return $this->database->execute("UPDATE {$table} SET reconciliation_status='required',reconciled_at=NULL,updated_at=%s WHERE privacy_state='anonymized'", [$this->time($now)]);
    }

    public function pendingReconciliation(): array
    {
        $states = $this->table(TableName::PRIVACY_STATES);
        $actions = $this->table(TableName::PRIVACY_ACTIONS);
        $rows = $this->database->fetchAll("SELECT a.* FROM {$states} s JOIN {$actions} a ON a.event_id=s.event_id AND a.privacy_action_id=s.privacy_action_id WHERE s.reconciliation_status='required' ORDER BY s.privacy_state_id ASC");
        return array_map(fn (array $row): PrivacyActionRecord => $this->action($row, new EventScope((int) $row['event_id'])), $rows);
    }

    private function assertSubjectExists(EventScope $scope, int $invitationId): void
    {
        $table = $this->table(TableName::INVITATIONS);
        if ($this->database->fetchValue("SELECT invitation_id FROM {$table} WHERE event_id=%d AND invitation_id=%d LIMIT 1 FOR UPDATE", [$scope->eventId, $invitationId]) === null) {
            throw new PrivacyException('resource_not_found');
        }
    }

    private function assertNoHold(EventScope $scope, int $invitationId): void
    {
        $table = $this->table(TableName::RETENTION_HOLDS);
        if ($this->database->fetchValue("SELECT retention_hold_id FROM {$table} WHERE event_id=%d AND hold_status='active' AND (invitation_id IS NULL OR invitation_id=%d) LIMIT 1 FOR UPDATE", [$scope->eventId, $invitationId]) !== null) {
            throw new PrivacyException('retention_hold_active');
        }
    }

    /** @param list<array<string, mixed>> $rows @return list<string> */
    private function locators(array $rows): array
    {
        return array_values(array_unique(array_filter(array_map(static fn (array $row): ?string => is_string($row['artifact_locator'] ?? null) ? $row['artifact_locator'] : null, $rows))));
    }

    /** @param array<string, mixed> $row */
    private function action(array $row, EventScope $scope): PrivacyActionRecord
    {
        return new PrivacyActionRecord((int) $row['privacy_action_id'], $scope, (int) $row['invitation_id'], (string) $row['request_kind'], (string) $row['policy_version'], (string) $row['purpose'], (string) $row['action_status'], (string) $row['checkpoint']);
    }

    private function time(DateTimeImmutable $date): string
    {
        return $date->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s');
    }
}
