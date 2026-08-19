<?php

namespace EventFlow\Infrastructure\Persistence\WordPress;

use DateTimeImmutable;
use DateTimeZone;
use EventFlow\Application\Authorization\EventRole;
use EventFlow\Application\Membership\{MembershipPage, MembershipQueryRepository, MembershipRecord, MembershipStatus};
use EventFlow\Application\Persistence\EventScope;
use EventFlow\Infrastructure\Persistence\{PersistenceException, TableName};

final class WpdbMembershipQueryRepository extends AbstractWpdbRepository implements MembershipQueryRepository
{
    public function list(EventScope $scope, int $limit, ?int $afterMembershipId): MembershipPage
    {
        if ($limit < 1 || $limit > 100 || ($afterMembershipId !== null && $afterMembershipId < 1)) {
            throw new PersistenceException('membership_query_invalid');
        }
        $table = $this->table(TableName::EVENT_MEMBERSHIPS);
        $after = $afterMembershipId === null ? '' : ' AND event_membership_id > %d';
        $parameters = [$scope->eventId];
        if ($afterMembershipId !== null) {
            $parameters[] = $afterMembershipId;
        }
        $parameters[] = $limit + 1;
        $rows = $this->database->fetchAll(
            "SELECT event_membership_id, event_id, user_id, event_role, membership_status, is_primary_owner, expires_at FROM {$table} WHERE event_id = %d{$after} ORDER BY event_membership_id ASC LIMIT %d",
            $parameters,
        );
        $hasMore = count($rows) > $limit;
        if ($hasMore) {
            array_pop($rows);
        }
        $records = array_map(fn (array $row): MembershipRecord => $this->hydrate($scope, $row), $rows);
        return new MembershipPage(
            $records,
            $hasMore && $records !== [] ? $records[array_key_last($records)]->membershipId : null,
        );
    }

    /** @param array<string, mixed> $row */
    private function hydrate(EventScope $scope, array $row): MembershipRecord
    {
        $role = EventRole::tryFrom((string) ($row['event_role'] ?? ''));
        $status = MembershipStatus::tryFrom((string) ($row['membership_status'] ?? ''));
        if ($role === null || $status === null || (int) ($row['event_id'] ?? 0) !== $scope->eventId) {
            throw new PersistenceException('membership_record_invalid');
        }
        $expiry = ($row['expires_at'] ?? null) === null
            ? null
            : new DateTimeImmutable((string) $row['expires_at'], new DateTimeZone('UTC'));
        return new MembershipRecord(
            (int) ($row['event_membership_id'] ?? 0),
            $scope,
            (int) ($row['user_id'] ?? 0),
            $role,
            $status,
            (bool) (int) ($row['is_primary_owner'] ?? 0),
            $expiry,
        );
    }
}
