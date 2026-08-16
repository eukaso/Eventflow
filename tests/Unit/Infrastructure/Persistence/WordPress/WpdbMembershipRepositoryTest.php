<?php

namespace EventFlow\Tests\Unit\Infrastructure\Persistence\WordPress;

use DateTimeImmutable;
use DateTimeZone;
use EventFlow\Application\Authorization\EventRole;
use EventFlow\Application\Membership\GrantMembership;
use EventFlow\Application\Membership\MembershipRecord;
use EventFlow\Application\Membership\MembershipStatus;
use EventFlow\Application\Persistence\EventScope;
use EventFlow\Infrastructure\Persistence\WordPress\WpdbAdapter;
use EventFlow\Infrastructure\Persistence\WordPress\WpdbMembershipRepository;
use EventFlow\Infrastructure\Persistence\WordPress\WpdbTableNames;
use PHPUnit\Framework\TestCase;

final class WpdbMembershipRepositoryTest extends TestCase
{
    public function testGrantUsesActiveNonPrimaryMembershipAndSqlNullExpiry(): void
    {
        $wpdb = new MembershipRepositoryWpdb();
        $wpdb->insert_id = 8;
        $created = $this->repository($wpdb)->grant(
            new GrantMembership(new EventScope(70), 19, EventRole::COORDINATOR),
            7,
            $this->now(),
        );

        self::assertSame(8, $created->membershipId);
        self::assertSame(MembershipStatus::ACTIVE, $created->status);
        self::assertFalse($created->isPrimaryOwner);
        self::assertStringContainsString("'active', 0, 7", $wpdb->queries[0]);
        self::assertStringContainsString('NULL', $wpdb->queries[0]);
    }

    public function testScopedLookupLocksAndHydratesCurrentRow(): void
    {
        $wpdb = new MembershipRepositoryWpdb();
        $wpdb->rows[] = $this->row(8, 19, 'organizer', 'suspended', false, null);

        $record = $this->repository($wpdb)->findForUpdate(new EventScope(70), 8);

        self::assertSame(MembershipStatus::SUSPENDED, $record?->status);
        self::assertStringContainsString('event_id = 70', $wpdb->queries[0]);
        self::assertStringContainsString('event_membership_id = 8', $wpdb->queries[0]);
        self::assertStringContainsString('FOR UPDATE', $wpdb->queries[0]);
    }

    public function testTransferUsesTwoGuardedWritesAndReturnsNonExpiringOwner(): void
    {
        $wpdb = new MembershipRepositoryWpdb();
        $repository = $this->repository($wpdb);
        $scope = new EventScope(70);
        $current = new MembershipRecord(1, $scope, 7, EventRole::OWNER, MembershipStatus::ACTIVE, true, null);
        $target = new MembershipRecord(
            8,
            $scope,
            19,
            EventRole::ORGANIZER,
            MembershipStatus::ACTIVE,
            false,
            new DateTimeImmutable('2026-09-01', new DateTimeZone('UTC')),
        );

        $updated = $repository->transferPrimaryOwner($current, $target, $this->now());

        self::assertTrue($updated->isPrimaryOwner);
        self::assertSame(EventRole::OWNER, $updated->role);
        self::assertNull($updated->expiresAt);
        self::assertCount(2, $wpdb->queries);
        self::assertStringContainsString('is_primary_owner = 0', $wpdb->queries[0]);
        self::assertStringContainsString('is_primary_owner = 1', $wpdb->queries[0]);
        self::assertStringContainsString('expires_at = NULL', $wpdb->queries[1]);
        self::assertStringContainsString("membership_status = 'active'", $wpdb->queries[1]);
    }

    public function testPrimaryOwnerLookupLocksAtMostTwoRowsToDetectCorruption(): void
    {
        $wpdb = new MembershipRepositoryWpdb();
        $wpdb->resultRows = [$this->row(1, 7, 'owner', 'active', true, null)];

        $owner = $this->repository($wpdb)->findPrimaryOwnerForUpdate(new EventScope(70));

        self::assertSame(1, $owner?->membershipId);
        self::assertStringContainsString('LIMIT 2 FOR UPDATE', $wpdb->queries[0]);
    }

    private function repository(MembershipRepositoryWpdb $wpdb): WpdbMembershipRepository
    {
        return new WpdbMembershipRepository(new WpdbAdapter($wpdb), new WpdbTableNames('wp_'));
    }

    /** @return array<string, mixed> */
    private function row(int $id, int $userId, string $role, string $status, bool $primary, ?string $expiresAt): array
    {
        return [
            'event_membership_id' => (string) $id,
            'event_id' => '70',
            'user_id' => (string) $userId,
            'event_role' => $role,
            'membership_status' => $status,
            'is_primary_owner' => $primary ? '1' : '0',
            'expires_at' => $expiresAt,
        ];
    }

    private function now(): DateTimeImmutable
    {
        return new DateTimeImmutable('2026-08-16 18:00:00', new DateTimeZone('UTC'));
    }
}

final class MembershipRepositoryWpdb
{
    public string $prefix = 'wp_';
    public string $last_error = '';
    public int $last_errno = 0;
    public int $insert_id = 1;
    /** @var list<string> */
    public array $queries = [];
    /** @var list<array<string, mixed>|null> */
    public array $rows = [];
    /** @var list<array<string, mixed>> */
    public array $resultRows = [];

    public function prepare(string $query, mixed ...$values): string
    {
        foreach ($values as $value) {
            $replacement = is_int($value) ? (string) $value : "'" . str_replace("'", "''", (string) $value) . "'";
            $query = (string) preg_replace('/%[dfs]/', $replacement, $query, 1);
        }
        return $query;
    }

    public function query(string $query): int { $this->queries[] = $query; return 1; }
    /** @return array<string, mixed>|null */
    public function get_row(string $query, string $format): ?array { $this->queries[] = $query; return array_shift($this->rows); }
    /** @return list<array<string, mixed>> */
    public function get_results(string $query, string $format): array { $this->queries[] = $query; return $this->resultRows; }
}
