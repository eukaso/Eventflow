<?php

namespace EventFlow\Infrastructure\Persistence\WordPress;

use DateTimeImmutable;
use DateTimeZone;
use EventFlow\Application\GuestAccess\GuestAccessRepository;
use EventFlow\Application\GuestAccess\GuestCredentialType;
use EventFlow\Application\GuestAccess\GuestSessionRecord;
use EventFlow\Application\Invitation\InvitationRecord;
use EventFlow\Application\Invitation\InvitationStatus;
use EventFlow\Application\Persistence\EventScope;
use EventFlow\Infrastructure\Persistence\PersistenceException;
use EventFlow\Infrastructure\Persistence\TableName;

final class WpdbGuestAccessRepository extends AbstractWpdbRepository implements GuestAccessRepository
{
    public function resolveBootstrapCredential(GuestCredentialType $type, string $digest, DateTimeImmutable $now): ?InvitationRecord
    {
        if (strlen($digest) !== 32) throw new PersistenceException('guest_credential_digest_invalid');
        $invitations = $this->table(TableName::INVITATIONS);
        if ($type === GuestCredentialType::INVITATION) {
            $row = $this->database->fetchRow(
                "SELECT i.invitation_id, i.event_id, i.invitation_code, i.primary_name, i.capacity, i.invitation_status, i.token_version, i.token_expires_at " .
                "FROM {$invitations} i WHERE i.token_lookup = %s AND i.invitation_status = %s AND i.token_revoked_at IS NULL " .
                'AND (i.token_expires_at IS NULL OR i.token_expires_at > %s) AND i.deleted_at IS NULL LIMIT 1 FOR UPDATE',
                [$digest, 'active', $this->timestamp($now)],
            );
        } else {
            $links = $this->table(TableName::GUEST_LINK_CREDENTIALS);
            $row = $this->database->fetchRow(
                "SELECT i.invitation_id, i.event_id, i.invitation_code, i.primary_name, i.capacity, i.invitation_status, i.token_version, i.token_expires_at " .
                "FROM {$links} c INNER JOIN {$invitations} i ON i.event_id = c.event_id AND i.invitation_id = c.invitation_id " .
                'WHERE c.credential_lookup = %s AND c.credential_status = %s AND c.expires_at > %s ' .
                'AND c.invitation_token_version = i.token_version AND i.invitation_status = %s AND i.token_revoked_at IS NULL ' .
                'AND (i.token_expires_at IS NULL OR i.token_expires_at > %s) AND i.deleted_at IS NULL LIMIT 1 FOR UPDATE',
                [$digest, 'active', $this->timestamp($now), 'active', $this->timestamp($now)],
            );
        }
        if ($row === null) return null;
        $scope = new EventScope((int) ($row['event_id'] ?? 0));
        $status = InvitationStatus::tryFrom((string) ($row['invitation_status'] ?? ''));
        if ($status === null) throw new PersistenceException('guest_invitation_record_invalid');

        return new InvitationRecord(
            (int) ($row['invitation_id'] ?? 0), $scope, (string) ($row['invitation_code'] ?? ''),
            (string) ($row['primary_name'] ?? ''), (int) ($row['capacity'] ?? 0), $status,
            (int) ($row['token_version'] ?? 0), $this->date($row['token_expires_at'] ?? null),
        );
    }

    public function markCredentialUsed(GuestCredentialType $type, string $digest, InvitationRecord $invitation, DateTimeImmutable $now): void
    {
        $timestamp = $this->timestamp($now);
        if ($type === GuestCredentialType::MESSAGE_LINK) {
            $links = $this->table(TableName::GUEST_LINK_CREDENTIALS);
            if ($this->database->execute(
                "UPDATE {$links} SET first_used_at = COALESCE(first_used_at, %s) WHERE credential_lookup = %s " .
                'AND event_id = %d AND invitation_id = %d AND invitation_token_version = %d AND credential_status = %s',
                [$timestamp, $digest, $invitation->eventScope->eventId, $invitation->invitationId, $invitation->tokenVersion, 'active'],
            ) !== 1) throw new PersistenceException('guest_link_use_conflict');
        }
        $table = $this->table(TableName::INVITATIONS);
        if ($this->database->execute(
            "UPDATE {$table} SET first_accessed_at = COALESCE(first_accessed_at, %s), last_accessed_at = %s, updated_at = %s " .
            'WHERE event_id = %d AND invitation_id = %d AND invitation_status = %s AND token_version = %d',
            [$timestamp, $timestamp, $timestamp, $invitation->eventScope->eventId, $invitation->invitationId, 'active', $invitation->tokenVersion],
        ) !== 1) throw new PersistenceException('guest_invitation_access_conflict');
    }

    public function createSession(InvitationRecord $invitation, string $sessionDigest, string $csrfDigest, DateTimeImmutable $expiresAt, DateTimeImmutable $now): GuestSessionRecord
    {
        if (strlen($sessionDigest) !== 32 || strlen($csrfDigest) !== 32 || $expiresAt <= $now) throw new PersistenceException('guest_session_create_invalid');
        $table = $this->table(TableName::GUEST_SESSIONS);
        if ($this->database->execute(
            "INSERT INTO {$table} (event_id, invitation_id, session_lookup, invitation_token_version, session_status, csrf_secret_digest, expires_at, created_at, updated_at) " .
            'VALUES (%d, %d, %s, %d, %s, %s, %s, %s, %s)',
            [$invitation->eventScope->eventId, $invitation->invitationId, $sessionDigest, $invitation->tokenVersion, 'active', $csrfDigest, $this->timestamp($expiresAt), $this->timestamp($now), $this->timestamp($now)],
        ) !== 1) throw new PersistenceException('guest_session_create_failed');

        return new GuestSessionRecord($this->database->lastInsertId(), $invitation->eventScope, $invitation->invitationId, $invitation->tokenVersion, $csrfDigest, $expiresAt);
    }

    public function findCurrentSession(string $sessionDigest, DateTimeImmutable $now): ?GuestSessionRecord
    {
        if (strlen($sessionDigest) !== 32) throw new PersistenceException('guest_session_digest_invalid');
        $sessions = $this->table(TableName::GUEST_SESSIONS);
        $invitations = $this->table(TableName::INVITATIONS);
        $row = $this->database->fetchRow(
            "SELECT s.guest_session_id, s.event_id, s.invitation_id, s.invitation_token_version, s.csrf_secret_digest, s.expires_at " .
            "FROM {$sessions} s INNER JOIN {$invitations} i ON i.event_id = s.event_id AND i.invitation_id = s.invitation_id " .
            'WHERE s.session_lookup = %s AND s.session_status = %s AND s.expires_at > %s ' .
            'AND s.invitation_token_version = i.token_version AND i.invitation_status = %s AND i.token_revoked_at IS NULL ' .
            'AND (i.token_expires_at IS NULL OR i.token_expires_at > %s) AND i.deleted_at IS NULL LIMIT 1 FOR UPDATE',
            [$sessionDigest, 'active', $this->timestamp($now), 'active', $this->timestamp($now)],
        );
        if ($row === null) return null;

        return new GuestSessionRecord(
            (int) ($row['guest_session_id'] ?? 0), new EventScope((int) ($row['event_id'] ?? 0)),
            (int) ($row['invitation_id'] ?? 0), (int) ($row['invitation_token_version'] ?? 0),
            (string) ($row['csrf_secret_digest'] ?? ''), $this->date($row['expires_at'] ?? null) ?? throw new PersistenceException('guest_session_expiry_invalid'),
        );
    }

    public function touchSession(GuestSessionRecord $session, DateTimeImmutable $now): void
    {
        $table = $this->table(TableName::GUEST_SESSIONS);
        $timestamp = $this->timestamp($now);
        if ($this->database->execute(
            "UPDATE {$table} SET last_seen_at = %s, updated_at = %s WHERE guest_session_id = %d AND event_id = %d " .
            'AND invitation_id = %d AND invitation_token_version = %d AND session_status = %s AND expires_at > %s',
            [$timestamp, $timestamp, $session->sessionId, $session->eventScope->eventId, $session->invitationId, $session->invitationTokenVersion, 'active', $timestamp],
        ) !== 1) throw new PersistenceException('guest_session_touch_conflict');
    }

    public function issueMessageLink(EventScope $scope, int $invitationId, int $messageId, string $purpose, string $digest, int $tokenVersion, DateTimeImmutable $expiresAt, DateTimeImmutable $now): int
    {
        if ($invitationId < 1 || $messageId < 1 || $tokenVersion < 1 || strlen($digest) !== 32 || $expiresAt <= $now) throw new PersistenceException('guest_link_create_invalid');
        $table = $this->table(TableName::GUEST_LINK_CREDENTIALS);
        if ($this->database->execute(
            "INSERT INTO {$table} (event_id, invitation_id, message_id, credential_lookup, credential_purpose, invitation_token_version, credential_status, expires_at, created_at) " .
            'VALUES (%d, %d, %d, %s, %s, %d, %s, %s, %s)',
            [$scope->eventId, $invitationId, $messageId, $digest, $purpose, $tokenVersion, 'active', $this->timestamp($expiresAt), $this->timestamp($now)],
        ) !== 1) throw new PersistenceException('guest_link_create_failed');

        return $this->database->lastInsertId();
    }

    private function timestamp(DateTimeImmutable $date): string { return $date->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s'); }
    private function date(mixed $value): ?DateTimeImmutable { return $value === null ? null : new DateTimeImmutable((string) $value, new DateTimeZone('UTC')); }
}
