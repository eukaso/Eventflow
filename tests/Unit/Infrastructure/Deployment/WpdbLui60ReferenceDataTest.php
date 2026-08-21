<?php

namespace EventFlow\Tests\Unit\Infrastructure\Deployment;

use EventFlow\Infrastructure\Deployment\WpdbLui60ReferenceData;
use EventFlow\Infrastructure\Persistence\WordPress\WpdbAdapter;
use EventFlow\Infrastructure\Persistence\WordPress\WpdbTableNames;
use PHPUnit\Framework\TestCase;

final class WpdbLui60ReferenceDataTest extends TestCase
{
    public function testCaptureReconcilesLegacyRowsImportMappingsResponsesAndCompanions(): void
    {
        $wpdb = new ReferenceWpdb(); $database = new WpdbAdapter($wpdb);
        $snapshot = (new WpdbLui60ReferenceData($database, new WpdbTableNames($database->tablePrefix())))->capture(9, 21);
        self::assertTrue($snapshot->sourceValid);
        self::assertSame(2, $snapshot->sourceInvitations);
        self::assertSame(3, $snapshot->sourceCapacity);
        self::assertSame(1, $snapshot->sourceAccepted);
        self::assertSame(1, $snapshot->sourcePending);
        self::assertSame(1, $snapshot->sourceCompanions);
        self::assertSame(2, $snapshot->matchedRows);
        self::assertSame(0, $snapshot->mismatchedRows);
        self::assertSame(0, $snapshot->orphanRows);
        self::assertMatchesRegularExpression('/^[a-f0-9]{64}$/', $snapshot->sourceFingerprint);
    }

    public function testExportWritesProtectedMappingColumnsAndOnlyReturnsSanitizedTotals(): void
    {
        $wpdb = new ReferenceWpdb(); $database = new WpdbAdapter($wpdb); $directory = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'eventflow-reference-' . bin2hex(random_bytes(6));
        self::assertTrue(mkdir($directory)); $path = $directory . DIRECTORY_SEPARATOR . 'reference.csv';
        try {
            $result = (new WpdbLui60ReferenceData($database, new WpdbTableNames($database->tablePrefix())))->export($path);
            self::assertSame(2, $result->invitations);
            self::assertSame(3, $result->capacity);
            self::assertSame(1, $result->accepted);
            $contents = file_get_contents($path); self::assertIsString($contents);
            self::assertStringStartsWith('primary_name,primary_email,primary_phone,capacity,legacy_guest_id,legacy_submitted,legacy_companion_names', $contents);
            self::assertSame(hash('sha256', $contents), $result->sha256);
            self::assertArrayNotHasKey('rows', $result->toArray());
        } finally {
            if (is_file($path)) unlink($path);
            if (is_dir($directory)) rmdir($directory);
        }
    }
}

final class ReferenceWpdb
{
    public string $prefix = 'wp_';
    public string $last_error = '';
    public int $last_errno = 0;
    public int $insert_id = 0;

    public function prepare(string $sql, mixed ...$parameters): string { return $sql; }
    public function get_var(string $sql): mixed { return str_contains($sql, 'information_schema.TABLES') ? 1 : null; }
    public function get_row(string $sql, string $format): ?array
    {
        return str_contains($sql, 'eventflow_import_jobs') ? ['import_status' => 'completed', 'total_rows' => 2, 'applied_rows' => 2, 'failed_rows' => 0] : null;
    }
    /** @return list<array<string,mixed>> */
    public function get_results(string $sql, string $format): array
    {
        if (str_contains($sql, 'lui60_event_guests')) return [
            ['guest_id' => 'G001', 'name' => 'Primary One', 'email' => 'one@example.test', 'phone' => '+10000000001', 'seats_reserved' => 2, 'companion_names' => '["Companion One"]', 'submitted_at' => '2026-08-20 12:00:00'],
            ['guest_id' => 'G002', 'name' => 'Primary Two', 'email' => '', 'phone' => '', 'seats_reserved' => 1, 'companion_names' => null, 'submitted_at' => null],
        ];
        if (str_contains($sql, 'eventflow_import_rows')) return [
            ['raw_data' => json_encode(['legacy_guest_id' => 'G001']), 'row_status' => 'applied', 'applied_invitation_id' => 101],
            ['raw_data' => json_encode(['legacy_guest_id' => 'G002']), 'row_status' => 'applied', 'applied_invitation_id' => 102],
        ];
        if (str_contains($sql, 'eventflow_invitations')) return [
            ['invitation_id' => 101, 'primary_name' => 'Primary One', 'primary_email' => 'one@example.test', 'primary_phone' => '+10000000001', 'capacity' => 2, 'response_status' => 'accepted'],
            ['invitation_id' => 102, 'primary_name' => 'Primary Two', 'primary_email' => null, 'primary_phone' => null, 'capacity' => 1, 'response_status' => 'pending'],
        ];
        if (str_contains($sql, 'eventflow_attendees')) return [
            ['invitation_id' => 101, 'display_name' => 'Primary One', 'attendee_role' => 'primary', 'attendance_status' => 'confirmed'],
            ['invitation_id' => 101, 'display_name' => 'Companion One', 'attendee_role' => 'companion', 'attendance_status' => 'confirmed'],
        ];
        return [];
    }
    public function query(string $sql): int|false { return 0; }
    public function esc_like(string $value): string { return $value; }
}
