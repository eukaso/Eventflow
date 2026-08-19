<?php

namespace EventFlow\Infrastructure\Persistence\WordPress;

use DateTimeImmutable;
use DateTimeZone;
use EventFlow\Application\Invitation\CreateInvitation;
use EventFlow\Application\Invitation\InvitationRecord;
use EventFlow\Application\Invitation\InvitationRepository;
use EventFlow\Application\Invitation\InvitationStatus;
use EventFlow\Application\Persistence\EventScope;
use EventFlow\Infrastructure\Persistence\PersistenceException;
use EventFlow\Infrastructure\Persistence\TableName;

final class WpdbInvitationRepository extends AbstractWpdbRepository implements InvitationRepository
{
    public function create(CreateInvitation $command, string $code, string $tokenDigest, ?int $actorUserId, DateTimeImmutable $now): InvitationRecord
    {
        $this->assertWrite($tokenDigest, $actorUserId);
        $table = $this->table(TableName::INVITATIONS);
        $email = $command->primaryEmail === null ? null : trim($command->primaryEmail);
        $phone = $command->primaryPhone === null ? null : trim($command->primaryPhone);
        $columns = ['event_id', 'invitation_code', 'primary_name', 'capacity', 'invitation_status', 'response_status', 'token_lookup', 'token_version'];
        $values = ['%d', '%s', '%s', '%d', '%s', '%s', '%s', '1'];
        $parameters = [$command->eventScope->eventId, $code, trim($command->primaryName), $command->capacity, 'active', 'pending', $tokenDigest];
        foreach ([
            ['primary_email', $email],
            ['primary_email_normalized', $email === null ? null : strtolower($email)],
            ['primary_phone', $phone],
            ['primary_phone_normalized', $phone === null ? null : preg_replace('/[^0-9+]/', '', $phone)],
            ['token_expires_at', $command->tokenExpiresAt === null ? null : $this->timestamp($command->tokenExpiresAt)],
        ] as [$column, $value]) {
            $columns[] = $column;
            $values[] = $value === null ? 'NULL' : '%s';
            if ($value !== null) $parameters[] = $value;
        }
        foreach ([['created_by_user_id', $actorUserId], ['updated_by_user_id', $actorUserId]] as [$column, $value]) {
            $columns[] = $column;
            $values[] = $value === null ? 'NULL' : '%d';
            if ($value !== null) $parameters[] = $value;
        }
        $columns = [...$columns, 'created_at', 'updated_at'];
        $values = [...$values, '%s', '%s'];
        $parameters[] = $this->timestamp($now);
        $parameters[] = $this->timestamp($now);
        if ($this->database->execute(
            "INSERT INTO {$table} (" . implode(', ', $columns) . ') VALUES (' . implode(', ', $values) . ')',
            $parameters,
        ) !== 1) throw new PersistenceException('invitation_create_failed');

        return new InvitationRecord(
            $this->database->lastInsertId(), $command->eventScope, $code, trim($command->primaryName),
            $command->capacity, InvitationStatus::ACTIVE, 1, $command->tokenExpiresAt,
            $email, $phone,
        );
    }

    public function lock(EventScope $scope, int $invitationId): ?InvitationRecord
    {
        if ($invitationId < 1) throw new PersistenceException('invalid_invitation_id');
        $table = $this->table(TableName::INVITATIONS);
        $row = $this->database->fetchRow(
            "SELECT invitation_id, event_id, invitation_code, primary_name, primary_email, primary_phone, capacity, invitation_status, response_status, token_version, token_expires_at, organizer_notes, invitation_revision, deleted_at " .
            "FROM {$table} WHERE event_id = %d AND invitation_id = %d AND deleted_at IS NULL LIMIT 1 FOR UPDATE",
            [$scope->eventId, $invitationId],
        );

        return $row === null ? null : $this->hydrate($row, $scope);
    }

    public function rotateCredential(InvitationRecord $invitation, string $tokenDigest, ?DateTimeImmutable $expiresAt, ?int $actorUserId, DateTimeImmutable $now): InvitationRecord
    {
        return $this->replaceCredential($invitation, $tokenDigest, $expiresAt, $actorUserId, $now, InvitationStatus::ACTIVE);
    }

    public function reactivate(InvitationRecord $invitation, string $tokenDigest, ?DateTimeImmutable $expiresAt, ?int $actorUserId, DateTimeImmutable $now): InvitationRecord
    {
        return $this->replaceCredential($invitation, $tokenDigest, $expiresAt, $actorUserId, $now, InvitationStatus::REVOKED);
    }

    public function revoke(InvitationRecord $invitation, ?int $actorUserId, DateTimeImmutable $now): InvitationRecord
    {
        $this->assertActor($actorUserId);
        $table = $this->table(TableName::INVITATIONS);
        $actorSql = $actorUserId === null ? 'NULL' : '%d';
        $parameters = [InvitationStatus::REVOKED->value, $this->timestamp($now)];
        if ($actorUserId !== null) $parameters[] = $actorUserId;
        array_push($parameters, $this->timestamp($now), $invitation->eventScope->eventId, $invitation->invitationId, InvitationStatus::ACTIVE->value, $invitation->tokenVersion, $invitation->revision);
        if ($this->database->execute(
            "UPDATE {$table} SET invitation_status = %s, token_revoked_at = %s, invitation_revision = invitation_revision + 1, updated_by_user_id = {$actorSql}, updated_at = %s " .
            'WHERE event_id = %d AND invitation_id = %d AND invitation_status = %s AND token_version = %d AND invitation_revision = %d AND deleted_at IS NULL',
            $parameters,
        ) !== 1) throw new PersistenceException('invitation_revoke_conflict');

        return new InvitationRecord(
            $invitation->invitationId, $invitation->eventScope, $invitation->code, $invitation->primaryName,
            $invitation->capacity, InvitationStatus::REVOKED, $invitation->tokenVersion, $invitation->tokenExpiresAt,
            $invitation->primaryEmail, $invitation->primaryPhone, $invitation->organizerNotes,
            $invitation->responseStatus, $invitation->revision + 1,
        );
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

    private function replaceCredential(InvitationRecord $invitation, string $digest, ?DateTimeImmutable $expiresAt, ?int $actorUserId, DateTimeImmutable $now, InvitationStatus $expected): InvitationRecord
    {
        $this->assertWrite($digest, $actorUserId);
        $table = $this->table(TableName::INVITATIONS);
        $expirySql = $expiresAt === null ? 'NULL' : '%s';
        $actorSql = $actorUserId === null ? 'NULL' : '%d';
        $parameters = [$digest, InvitationStatus::ACTIVE->value];
        if ($expiresAt !== null) $parameters[] = $this->timestamp($expiresAt);
        if ($actorUserId !== null) $parameters[] = $actorUserId;
        array_push($parameters, $this->timestamp($now), $invitation->eventScope->eventId, $invitation->invitationId, $expected->value, $invitation->tokenVersion, $invitation->revision);
        if ($this->database->execute(
            "UPDATE {$table} SET token_lookup = %s, token_version = token_version + 1, invitation_status = %s, " .
            "token_expires_at = {$expirySql}, token_revoked_at = NULL, invitation_revision = invitation_revision + 1, updated_by_user_id = {$actorSql}, updated_at = %s " .
            'WHERE event_id = %d AND invitation_id = %d AND invitation_status = %s AND token_version = %d AND invitation_revision = %d AND deleted_at IS NULL',
            $parameters,
        ) !== 1) throw new PersistenceException('invitation_credential_conflict');

        return new InvitationRecord(
            $invitation->invitationId, $invitation->eventScope, $invitation->code, $invitation->primaryName,
            $invitation->capacity, InvitationStatus::ACTIVE, $invitation->tokenVersion + 1, $expiresAt,
            $invitation->primaryEmail, $invitation->primaryPhone, $invitation->organizerNotes,
            $invitation->responseStatus, $invitation->revision + 1,
        );
    }

    /** @param array<string, mixed> $row */
    private function hydrate(array $row, EventScope $scope): InvitationRecord
    {
        $status = InvitationStatus::tryFrom((string) ($row['invitation_status'] ?? ''));
        if ($status === null || (int) ($row['event_id'] ?? 0) !== $scope->eventId) throw new PersistenceException('invitation_record_invalid');
        return new InvitationRecord(
            (int) ($row['invitation_id'] ?? 0), $scope, (string) ($row['invitation_code'] ?? ''),
            (string) ($row['primary_name'] ?? ''), (int) ($row['capacity'] ?? 0), $status,
            (int) ($row['token_version'] ?? 0), $this->date($row['token_expires_at'] ?? null),
            isset($row['primary_email']) ? (string) $row['primary_email'] : null,
            isset($row['primary_phone']) ? (string) $row['primary_phone'] : null,
            isset($row['organizer_notes']) ? (string) $row['organizer_notes'] : null,
            (string) ($row['response_status'] ?? 'pending'),
            (int) ($row['invitation_revision'] ?? 1),
            $this->date($row['deleted_at'] ?? null),
        );
    }

    private function assertWrite(string $digest, ?int $actorUserId): void
    {
        if (strlen($digest) !== 32) throw new PersistenceException('invitation_write_invalid');
        $this->assertActor($actorUserId);
    }
    private function assertActor(?int $actorUserId): void { if ($actorUserId !== null && $actorUserId < 1) throw new PersistenceException('invitation_write_invalid'); }
    private function timestamp(DateTimeImmutable $date): string { return $date->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s'); }
    private function date(mixed $value): ?DateTimeImmutable { return $value === null ? null : new DateTimeImmutable((string) $value, new DateTimeZone('UTC')); }
}
