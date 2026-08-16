<?php

namespace EventFlow\Tests\Unit\Infrastructure\Persistence\WordPress;

use EventFlow\Application\Authorization\EventRole;
use EventFlow\Application\Persistence\EventScope;
use EventFlow\Infrastructure\Persistence\PersistenceException;
use EventFlow\Infrastructure\Persistence\WordPress\WpdbAdapter;
use EventFlow\Infrastructure\Persistence\WordPress\WpdbMembershipReader;
use EventFlow\Infrastructure\Persistence\WordPress\WpdbTableNames;
use PHPUnit\Framework\TestCase;

final class WpdbMembershipReaderTest extends TestCase
{
    public function testMapsCurrentEventScopedMembership(): void
    {
        $wpdb = new MembershipFakeWpdb();
        $wpdb->row = [
            'event_membership_id' => '8',
            'event_id' => '10',
            'user_id' => '7',
            'event_role' => 'organizer',
            'is_primary_owner' => '0',
            'expires_at' => '2026-09-01 12:00:00',
        ];
        $reader = $this->reader($wpdb);

        $membership = $reader->findCurrent(new EventScope(10), 7);

        self::assertNotNull($membership);
        self::assertSame(8, $membership->membershipId);
        self::assertSame(EventRole::ORGANIZER, $membership->role);
        self::assertSame('2026-09-01 12:00:00', $membership->expiresAt?->format('Y-m-d H:i:s'));
        self::assertStringContainsString('event_id = 10', $wpdb->lastQuery);
        self::assertStringContainsString('user_id = 7', $wpdb->lastQuery);
        self::assertStringContainsString("membership_status = 'active'", $wpdb->lastQuery);
    }

    public function testUnknownRoleFailsClosed(): void
    {
        $wpdb = new MembershipFakeWpdb();
        $wpdb->row = [
            'event_membership_id' => '8',
            'event_id' => '10',
            'user_id' => '7',
            'event_role' => 'super_admin_surprise',
            'is_primary_owner' => '0',
            'expires_at' => null,
        ];

        $this->expectException(PersistenceException::class);
        $this->expectExceptionMessage('membership_role_unknown');
        $this->reader($wpdb)->findCurrent(new EventScope(10), 7);
    }

    public function testInvalidPrimaryOwnerStateFailsClosed(): void
    {
        $wpdb = new MembershipFakeWpdb();
        $wpdb->row = [
            'event_membership_id' => '8',
            'event_id' => '10',
            'user_id' => '7',
            'event_role' => 'owner',
            'is_primary_owner' => '1',
            'expires_at' => '2026-09-01 12:00:00',
        ];

        $this->expectException(PersistenceException::class);
        $this->expectExceptionMessage('primary_owner_membership_invalid');
        $this->reader($wpdb)->findCurrent(new EventScope(10), 7);
    }

    private function reader(MembershipFakeWpdb $wpdb): WpdbMembershipReader
    {
        $database = new WpdbAdapter($wpdb);
        return new WpdbMembershipReader($database, new WpdbTableNames($database->tablePrefix()));
    }
}

final class MembershipFakeWpdb
{
    public string $prefix = 'wp_';
    public string $last_error = '';
    /** @var array<string, mixed>|null */
    public ?array $row = null;
    public string $lastQuery = '';

    public function prepare(string $query, mixed ...$values): string
    {
        return vsprintf(str_replace(['%s', '%d'], ["'%s'", '%d'], $query), $values);
    }

    /** @return array<string, mixed>|null */
    public function get_row(string $query, string $output): ?array
    {
        $this->lastQuery = $query;
        return $this->row;
    }
}
