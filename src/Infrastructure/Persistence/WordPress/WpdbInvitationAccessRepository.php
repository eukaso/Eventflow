<?php

namespace EventFlow\Infrastructure\Persistence\WordPress;

use DateTimeImmutable;
use DateTimeZone;
use EventFlow\Application\Invitation\{InvitationAccessRepository, InvitationPage, InvitationRecord, InvitationStatus};
use EventFlow\Application\Persistence\EventScope;
use EventFlow\Infrastructure\Persistence\{PersistenceException, TableName};

final class WpdbInvitationAccessRepository extends AbstractWpdbRepository implements InvitationAccessRepository
{
    public function list(EventScope $scope, int $limit, ?int $afterInvitationId): InvitationPage
    {
        if ($limit < 1 || $limit > 100 || ($afterInvitationId !== null && $afterInvitationId < 1)) {
            throw new PersistenceException('invitation_query_invalid');
        }
        $table = $this->table(TableName::INVITATIONS);
        $after = $afterInvitationId === null ? '' : ' AND invitation_id > %d';
        $parameters = [$scope->eventId];
        if ($afterInvitationId !== null) {
            $parameters[] = $afterInvitationId;
        }
        $parameters[] = $limit + 1;
        $rows = $this->database->fetchAll(
            'SELECT ' . $this->columns() . " FROM {$table} WHERE event_id = %d AND deleted_at IS NULL{$after} ORDER BY invitation_id ASC LIMIT %d",
            $parameters,
        );
        $hasMore = count($rows) > $limit;
        if ($hasMore) {
            array_pop($rows);
        }
        $records = array_map(fn (array $row): InvitationRecord => $this->hydrate($scope, $row), $rows);
        return new InvitationPage(
            $records,
            $hasMore && $records !== [] ? $records[array_key_last($records)]->invitationId : null,
        );
    }

    public function find(EventScope $scope, int $invitationId): ?InvitationRecord
    {
        return $this->findRecord($scope, $invitationId, false, false);
    }

    public function lock(EventScope $scope, int $invitationId, bool $archived): ?InvitationRecord
    {
        return $this->findRecord($scope, $invitationId, true, $archived);
    }

    public function activeAttendeeCount(EventScope $scope, int $invitationId): int
    {
        $table = $this->table(TableName::ATTENDEES);
        return (int) $this->database->fetchValue(
            "SELECT COUNT(*) FROM {$table} WHERE event_id = %d AND invitation_id = %d AND attendance_status IN (%s, %s) AND deleted_at IS NULL",
            [$scope->eventId, $invitationId, 'pending', 'confirmed'],
        );
    }

    public function applyCompanionRollout(EventScope $scope, int $totalCapacity, int $actorUserId, DateTimeImmutable $now): int
    {
        $this->assertActor($actorUserId);
        if ($totalCapacity < 1) throw new PersistenceException('invitation_capacity_invalid');
        $invitations = $this->table(TableName::INVITATIONS);
        $attendees = $this->table(TableName::ATTENDEES);
        return $this->database->execute(
            "UPDATE {$invitations} i SET i.capacity=%d,i.invitation_revision=i.invitation_revision+1,i.updated_by_user_id=%d,i.updated_at=%s " .
            "WHERE i.event_id=%d AND i.deleted_at IS NULL AND i.capacity>%d AND " .
            "(SELECT COUNT(*) FROM {$attendees} a WHERE a.event_id=i.event_id AND a.invitation_id=i.invitation_id " .
            "AND a.attendance_status IN (%s,%s) AND a.deleted_at IS NULL)<=%d",
            [$totalCapacity, $actorUserId, $this->timestamp($now), $scope->eventId, $totalCapacity, 'pending', 'confirmed', $totalCapacity],
        );
    }

    public function update(
        InvitationRecord $current,
        InvitationRecord $replacement,
        int $actorUserId,
        DateTimeImmutable $now,
    ): InvitationRecord {
        $this->assertActor($actorUserId);
        $table = $this->table(TableName::INVITATIONS);
        $parameters = [$replacement->primaryName];
        $emailSql = $this->nullable($replacement->primaryEmail, '%s', $parameters);
        $emailNormalizedSql = $this->nullable(
            $replacement->primaryEmail === null ? null : strtolower($replacement->primaryEmail),
            '%s',
            $parameters,
        );
        $phoneSql = $this->nullable($replacement->primaryPhone, '%s', $parameters);
        $phoneNormalizedSql = $this->nullable(
            $replacement->primaryPhone === null ? null : preg_replace('/[^0-9+]/', '', $replacement->primaryPhone),
            '%s',
            $parameters,
        );
        $parameters[] = $replacement->capacity;
        $notesSql = $this->nullable($replacement->organizerNotes, '%s', $parameters);
        array_push(
            $parameters,
            $actorUserId,
            $this->timestamp($now),
            $current->eventScope->eventId,
            $current->invitationId,
            $current->revision,
        );
        $affected = $this->database->execute(
            "UPDATE {$table} SET primary_name = %s, primary_email = {$emailSql}, primary_email_normalized = {$emailNormalizedSql}, " .
            "primary_phone = {$phoneSql}, primary_phone_normalized = {$phoneNormalizedSql}, capacity = %d, organizer_notes = {$notesSql}, " .
            'invitation_revision = invitation_revision + 1, updated_by_user_id = %d, updated_at = %s ' .
            'WHERE event_id = %d AND invitation_id = %d AND invitation_revision = %d AND deleted_at IS NULL',
            $parameters,
        );
        if ($affected !== 1) {
            throw new PersistenceException('resource_modified');
        }
        return $this->withLifecycle($replacement, $current->revision + 1, null);
    }

    public function archive(InvitationRecord $current, int $actorUserId, DateTimeImmutable $now): InvitationRecord
    {
        $this->assertActor($actorUserId);
        $table = $this->table(TableName::INVITATIONS);
        $timestamp = $this->timestamp($now);
        $affected = $this->database->execute(
            "UPDATE {$table} SET deleted_at = %s, invitation_revision = invitation_revision + 1, updated_by_user_id = %d, updated_at = %s " .
            'WHERE event_id = %d AND invitation_id = %d AND invitation_revision = %d AND invitation_status = %s AND deleted_at IS NULL',
            [$timestamp, $actorUserId, $timestamp, $current->eventScope->eventId, $current->invitationId, $current->revision, InvitationStatus::REVOKED->value],
        );
        if ($affected !== 1) {
            throw new PersistenceException('resource_modified');
        }
        return $this->withLifecycle($current, $current->revision + 1, $now);
    }

    public function restore(InvitationRecord $current, int $actorUserId, DateTimeImmutable $now): InvitationRecord
    {
        $this->assertActor($actorUserId);
        $table = $this->table(TableName::INVITATIONS);
        $affected = $this->database->execute(
            "UPDATE {$table} SET deleted_at = NULL, invitation_revision = invitation_revision + 1, updated_by_user_id = %d, updated_at = %s " .
            'WHERE event_id = %d AND invitation_id = %d AND invitation_revision = %d AND invitation_status = %s AND deleted_at IS NOT NULL',
            [$actorUserId, $this->timestamp($now), $current->eventScope->eventId, $current->invitationId, $current->revision, InvitationStatus::REVOKED->value],
        );
        if ($affected !== 1) {
            throw new PersistenceException('resource_modified');
        }
        return $this->withLifecycle($current, $current->revision + 1, null);
    }

    public function invalidateGuestAccess(EventScope $scope, int $invitationId, DateTimeImmutable $now): void
    {
        $timestamp = $this->timestamp($now);
        $sessions = $this->table(TableName::GUEST_SESSIONS);
        $links = $this->table(TableName::GUEST_LINK_CREDENTIALS);
        $this->database->execute(
            "UPDATE {$sessions} SET session_status = %s, revoked_at = %s, updated_at = %s WHERE event_id = %d AND invitation_id = %d AND session_status = %s",
            ['revoked', $timestamp, $timestamp, $scope->eventId, $invitationId, 'active'],
        );
        $this->database->execute(
            "UPDATE {$links} SET credential_status = %s, revoked_at = %s WHERE event_id = %d AND invitation_id = %d AND credential_status = %s",
            ['revoked', $timestamp, $scope->eventId, $invitationId, 'active'],
        );
    }

    private function findRecord(EventScope $scope, int $invitationId, bool $lock, bool $archived): ?InvitationRecord
    {
        if ($invitationId < 1) {
            throw new PersistenceException('invitation_id_invalid');
        }
        $table = $this->table(TableName::INVITATIONS);
        $deleted = $archived ? 'deleted_at IS NOT NULL' : 'deleted_at IS NULL';
        $row = $this->database->fetchRow(
            'SELECT ' . $this->columns() . " FROM {$table} WHERE event_id = %d AND invitation_id = %d AND {$deleted}" . ($lock ? ' FOR UPDATE' : ''),
            [$scope->eventId, $invitationId],
        );
        return $row === null ? null : $this->hydrate($scope, $row);
    }

    /** @param array<string, mixed> $row */
    private function hydrate(EventScope $scope, array $row): InvitationRecord
    {
        $status = InvitationStatus::tryFrom((string) ($row['invitation_status'] ?? ''));
        if ($status === null || (int) ($row['event_id'] ?? 0) !== $scope->eventId) {
            throw new PersistenceException('invitation_record_invalid');
        }
        return new InvitationRecord(
            (int) ($row['invitation_id'] ?? 0),
            $scope,
            (string) ($row['invitation_code'] ?? ''),
            (string) ($row['primary_name'] ?? ''),
            (int) ($row['capacity'] ?? 0),
            $status,
            (int) ($row['token_version'] ?? 0),
            $this->date($row['token_expires_at'] ?? null),
            isset($row['primary_email']) ? (string) $row['primary_email'] : null,
            isset($row['primary_phone']) ? (string) $row['primary_phone'] : null,
            isset($row['organizer_notes']) ? (string) $row['organizer_notes'] : null,
            (string) ($row['response_status'] ?? ''),
            (int) ($row['invitation_revision'] ?? 0),
            $this->date($row['deleted_at'] ?? null),
        );
    }

    private function withLifecycle(InvitationRecord $record, int $revision, ?DateTimeImmutable $archivedAt): InvitationRecord
    {
        return new InvitationRecord(
            $record->invitationId,
            $record->eventScope,
            $record->code,
            $record->primaryName,
            $record->capacity,
            $record->status,
            $record->tokenVersion,
            $record->tokenExpiresAt,
            $record->primaryEmail,
            $record->primaryPhone,
            $record->organizerNotes,
            $record->responseStatus,
            $revision,
            $archivedAt,
        );
    }

    /** @param list<mixed> $parameters */
    private function nullable(mixed $value, string $placeholder, array &$parameters): string
    {
        if ($value === null) {
            return 'NULL';
        }
        $parameters[] = $value;
        return $placeholder;
    }

    private function columns(): string
    {
        return 'invitation_id,event_id,invitation_code,primary_name,primary_email,primary_phone,capacity,invitation_status,response_status,token_version,token_expires_at,organizer_notes,invitation_revision,deleted_at';
    }

    private function assertActor(int $actorUserId): void
    {
        if ($actorUserId < 1) {
            throw new PersistenceException('invitation_actor_invalid');
        }
    }

    private function timestamp(DateTimeImmutable $date): string
    {
        return $date->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s');
    }

    private function date(mixed $value): ?DateTimeImmutable
    {
        return $value === null ? null : new DateTimeImmutable((string) $value, new DateTimeZone('UTC'));
    }
}
