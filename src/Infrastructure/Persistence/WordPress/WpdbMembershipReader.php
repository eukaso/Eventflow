<?php

namespace EventFlow\Infrastructure\Persistence\WordPress;

use DateTimeImmutable;
use DateTimeZone;
use EventFlow\Application\Authorization\EventRole;
use EventFlow\Application\Authorization\MembershipReader;
use EventFlow\Application\Authorization\MembershipSnapshot;
use EventFlow\Application\Persistence\EventScope;
use EventFlow\Infrastructure\Persistence\PersistenceException;
use EventFlow\Infrastructure\Persistence\TableName;

final class WpdbMembershipReader extends AbstractWpdbRepository implements MembershipReader
{
    public function findCurrent(EventScope $eventScope, int $userId): ?MembershipSnapshot
    {
        if ($userId < 1) {
            throw new PersistenceException('invalid_membership_user_id');
        }

        $table = $this->table(TableName::EVENT_MEMBERSHIPS);
        $row = $this->database->fetchRow(
            "SELECT event_membership_id, event_id, user_id, event_role, is_primary_owner, expires_at " .
            "FROM {$table} WHERE event_id = %d AND user_id = %d AND membership_status = %s LIMIT 1",
            [$eventScope->eventId, $userId, 'active'],
        );

        if ($row === null) {
            return null;
        }

        $role = EventRole::tryFrom((string) ($row['event_role'] ?? ''));

        if ($role === null) {
            throw new PersistenceException('membership_role_unknown');
        }

        $membershipId = (int) ($row['event_membership_id'] ?? 0);
        $rowEventId = (int) ($row['event_id'] ?? 0);
        $rowUserId = (int) ($row['user_id'] ?? 0);

        if ($membershipId < 1 || $rowEventId !== $eventScope->eventId || $rowUserId !== $userId) {
            throw new PersistenceException('membership_scope_mismatch');
        }

        $isPrimaryOwner = (int) ($row['is_primary_owner'] ?? 0) === 1;
        $expiresAt = $this->parseExpiry($row['expires_at'] ?? null);

        if ($isPrimaryOwner && ($role !== EventRole::OWNER || $expiresAt !== null)) {
            throw new PersistenceException('primary_owner_membership_invalid');
        }

        return new MembershipSnapshot(
            membershipId: $membershipId,
            eventScope: new EventScope($rowEventId),
            userId: $rowUserId,
            role: $role,
            isPrimaryOwner: $isPrimaryOwner,
            expiresAt: $expiresAt,
        );
    }

    private function parseExpiry(mixed $value): ?DateTimeImmutable
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (!is_string($value)) {
            throw new PersistenceException('membership_expiry_invalid');
        }

        $expiry = DateTimeImmutable::createFromFormat(
            '!Y-m-d H:i:s',
            $value,
            new DateTimeZone('UTC'),
        );

        if ($expiry === false) {
            throw new PersistenceException('membership_expiry_invalid');
        }

        return $expiry;
    }
}
