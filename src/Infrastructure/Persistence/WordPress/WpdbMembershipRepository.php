<?php

namespace EventFlow\Infrastructure\Persistence\WordPress;

use DateTimeImmutable;
use DateTimeZone;
use EventFlow\Application\Authorization\EventRole;
use EventFlow\Application\Membership\GrantMembership;
use EventFlow\Application\Membership\MembershipRecord;
use EventFlow\Application\Membership\MembershipRepository;
use EventFlow\Application\Membership\MembershipStatus;
use EventFlow\Application\Persistence\EventScope;
use EventFlow\Infrastructure\Persistence\PersistenceException;
use EventFlow\Infrastructure\Persistence\TableName;

final class WpdbMembershipRepository extends AbstractWpdbRepository implements MembershipRepository
{
    public function findForUpdate(EventScope $scope, int $membershipId): ?MembershipRecord
    {
        if ($membershipId < 1) {
            throw new PersistenceException('invalid_membership_id');
        }

        return $this->findOne('event_id = %d AND event_membership_id = %d', [$scope->eventId, $membershipId], true, $scope);
    }

    public function findByUserForUpdate(EventScope $scope, int $userId): ?MembershipRecord
    {
        if ($userId < 1) {
            throw new PersistenceException('invalid_membership_user_id');
        }

        return $this->findOne('event_id = %d AND user_id = %d', [$scope->eventId, $userId], true, $scope);
    }

    public function findPrimaryOwnerForUpdate(EventScope $scope): ?MembershipRecord
    {
        $table = $this->table(TableName::EVENT_MEMBERSHIPS);
        $rows = $this->database->fetchAll(
            "SELECT event_membership_id, event_id, user_id, event_role, membership_status, is_primary_owner, expires_at " .
            "FROM {$table} WHERE event_id = %d AND is_primary_owner = 1 AND membership_status = %s LIMIT 2 FOR UPDATE",
            [$scope->eventId, MembershipStatus::ACTIVE->value],
        );
        if (count($rows) > 1) {
            throw new PersistenceException('multiple_primary_owners_detected');
        }

        return isset($rows[0]) ? $this->hydrate($rows[0], $scope) : null;
    }

    public function grant(GrantMembership $command, ?int $actorUserId, DateTimeImmutable $now): MembershipRecord
    {
        if ($actorUserId !== null && $actorUserId < 1) {
            throw new PersistenceException('invalid_membership_actor');
        }
        $table = $this->table(TableName::EVENT_MEMBERSHIPS);
        $actorSql = $actorUserId === null ? 'NULL' : '%d';
        $parameters = [
            $command->eventScope->eventId,
            $command->userId,
            $command->role->value,
            MembershipStatus::ACTIVE->value,
        ];
        if ($actorUserId !== null) {
            $parameters[] = $actorUserId;
        }
        $parameters[] = $this->timestamp($now);
        $expirySql = $command->expiresAt === null ? 'NULL' : '%s';
        if ($command->expiresAt !== null) {
            $parameters[] = $this->timestamp($command->expiresAt);
        }
        $parameters[] = $this->timestamp($now);
        $parameters[] = $this->timestamp($now);
        $affected = $this->database->execute(
            "INSERT INTO {$table} (event_id, user_id, event_role, membership_status, is_primary_owner, granted_by_user_id, " .
            "granted_at, expires_at, created_at, updated_at) VALUES (%d, %d, %s, %s, 0, {$actorSql}, %s, {$expirySql}, %s, %s)",
            $parameters,
        );
        if ($affected !== 1) {
            throw new PersistenceException('membership_grant_failed');
        }

        return new MembershipRecord(
            $this->database->lastInsertId(),
            $command->eventScope,
            $command->userId,
            $command->role,
            MembershipStatus::ACTIVE,
            false,
            $command->expiresAt,
        );
    }

    public function change(MembershipRecord $current, EventRole $role, ?DateTimeImmutable $expiresAt, DateTimeImmutable $now): MembershipRecord
    {
        $table = $this->table(TableName::EVENT_MEMBERSHIPS);
        $expirySql = $expiresAt === null ? 'NULL' : '%s';
        $parameters = [$role->value];
        if ($expiresAt !== null) {
            $parameters[] = $this->timestamp($expiresAt);
        }
        array_push(
            $parameters,
            $this->timestamp($now),
            $current->eventScope->eventId,
            $current->membershipId,
            $current->status->value,
            $current->isPrimaryOwner ? 1 : 0,
        );
        $affected = $this->database->execute(
            "UPDATE {$table} SET event_role = %s, expires_at = {$expirySql}, updated_at = %s " .
            'WHERE event_id = %d AND event_membership_id = %d AND membership_status = %s AND is_primary_owner = %d',
            $parameters,
        );
        if ($affected !== 1) {
            throw new PersistenceException('membership_change_conflict');
        }

        return new MembershipRecord(
            $current->membershipId,
            $current->eventScope,
            $current->userId,
            $role,
            $current->status,
            $current->isPrimaryOwner,
            $expiresAt,
        );
    }

    public function transitionStatus(MembershipRecord $current, MembershipStatus $status, DateTimeImmutable $now): MembershipRecord
    {
        $table = $this->table(TableName::EVENT_MEMBERSHIPS);
        $revokedSql = $status === MembershipStatus::REVOKED ? '%s' : 'NULL';
        $parameters = [$status->value];
        if ($status === MembershipStatus::REVOKED) {
            $parameters[] = $this->timestamp($now);
        }
        array_push(
            $parameters,
            $this->timestamp($now),
            $current->eventScope->eventId,
            $current->membershipId,
            $current->status->value,
            $current->isPrimaryOwner ? 1 : 0,
        );
        $affected = $this->database->execute(
            "UPDATE {$table} SET membership_status = %s, revoked_at = {$revokedSql}, updated_at = %s " .
            'WHERE event_id = %d AND event_membership_id = %d AND membership_status = %s AND is_primary_owner = %d',
            $parameters,
        );
        if ($affected !== 1) {
            throw new PersistenceException('membership_status_conflict');
        }

        return new MembershipRecord(
            $current->membershipId,
            $current->eventScope,
            $current->userId,
            $current->role,
            $status,
            $current->isPrimaryOwner,
            $current->expiresAt,
        );
    }

    public function transferPrimaryOwner(MembershipRecord $current, MembershipRecord $target, DateTimeImmutable $now): MembershipRecord
    {
        if ($current->eventScope->eventId !== $target->eventScope->eventId || !$current->isPrimaryOwner) {
            throw new PersistenceException('primary_owner_transfer_scope_invalid');
        }
        $table = $this->table(TableName::EVENT_MEMBERSHIPS);
        $timestamp = $this->timestamp($now);
        if ($this->database->execute(
            "UPDATE {$table} SET is_primary_owner = 0, updated_at = %s " .
            'WHERE event_id = %d AND event_membership_id = %d AND is_primary_owner = 1 AND membership_status = %s',
            [$timestamp, $current->eventScope->eventId, $current->membershipId, MembershipStatus::ACTIVE->value],
        ) !== 1) {
            throw new PersistenceException('primary_owner_transfer_conflict');
        }
        if ($this->database->execute(
            "UPDATE {$table} SET event_role = %s, membership_status = %s, is_primary_owner = 1, expires_at = NULL, updated_at = %s " .
            'WHERE event_id = %d AND event_membership_id = %d AND is_primary_owner = 0 AND membership_status = %s',
            [
                EventRole::OWNER->value,
                MembershipStatus::ACTIVE->value,
                $timestamp,
                $target->eventScope->eventId,
                $target->membershipId,
                MembershipStatus::ACTIVE->value,
            ],
        ) !== 1) {
            throw new PersistenceException('primary_owner_transfer_conflict');
        }

        return new MembershipRecord(
            $target->membershipId,
            $target->eventScope,
            $target->userId,
            EventRole::OWNER,
            MembershipStatus::ACTIVE,
            true,
            null,
        );
    }

    /** @param list<int|string> $parameters */
    private function findOne(string $where, array $parameters, bool $forUpdate, EventScope $scope): ?MembershipRecord
    {
        $table = $this->table(TableName::EVENT_MEMBERSHIPS);
        $row = $this->database->fetchRow(
            "SELECT event_membership_id, event_id, user_id, event_role, membership_status, is_primary_owner, expires_at " .
            "FROM {$table} WHERE {$where} LIMIT 1" . ($forUpdate ? ' FOR UPDATE' : ''),
            $parameters,
        );
        if ($row === null) {
            return null;
        }

        return $this->hydrate($row, $scope);
    }

    /** @param array<string, mixed> $row */
    private function hydrate(array $row, EventScope $scope): MembershipRecord
    {
        $role = EventRole::tryFrom((string) ($row['event_role'] ?? ''));
        $status = MembershipStatus::tryFrom((string) ($row['membership_status'] ?? ''));
        $membershipId = (int) ($row['event_membership_id'] ?? 0);
        $eventId = (int) ($row['event_id'] ?? 0);
        $userId = (int) ($row['user_id'] ?? 0);
        if ($role === null || $status === null || $membershipId < 1 || $eventId !== $scope->eventId || $userId < 1) {
            throw new PersistenceException('membership_record_invalid');
        }

        return new MembershipRecord(
            $membershipId,
            $scope,
            $userId,
            $role,
            $status,
            (int) ($row['is_primary_owner'] ?? 0) === 1,
            isset($row['expires_at']) ? new DateTimeImmutable((string) $row['expires_at'], new DateTimeZone('UTC')) : null,
        );
    }

    private function timestamp(DateTimeImmutable $date): string
    {
        return $date->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s');
    }
}
