<?php

namespace EventFlow\Tests\Unit\Infrastructure\Persistence\WordPress;

use DateTimeImmutable;
use DateTimeZone;
use EventFlow\Application\Import\ImportRowRecord;
use EventFlow\Application\Import\ImportRowStatus;
use EventFlow\Application\Import\ParsedImportSource;
use EventFlow\Application\Persistence\EventScope;
use EventFlow\Infrastructure\Persistence\WordPress\WpdbAdapter;
use EventFlow\Infrastructure\Persistence\WordPress\WpdbImportRepository;
use EventFlow\Infrastructure\Persistence\WordPress\WpdbTableNames;
use PHPUnit\Framework\TestCase;

final class WpdbImportRepositoryTest extends TestCase
{
    public function testStagesJobAndRowsInsideCallerTransactionShape(): void
    {
        $wpdb = new ImportRepositoryWpdb(); $wpdb->insert_id = 5;
        $job = $this->repository($wpdb)->createStaged(new EventScope(100), new ParsedImportSource('guests.csv', str_repeat('a', 64), ['Name'], [['Name' => 'One'], ['Name' => 'Two']]), [['Name' => 'One'], ['Name' => 'Two']], 7, $this->now());
        self::assertSame(5, $job->jobId); self::assertCount(3, $wpdb->queries); self::assertStringContainsString('INSERT INTO wp_eventflow_import_jobs', $wpdb->queries[0]); self::assertStringContainsString('INSERT INTO wp_eventflow_import_rows', $wpdb->queries[1]);
    }

    public function testLeaseCanOnlyReplaceMissingOrExpiredLease(): void
    {
        $wpdb = new ImportRepositoryWpdb(); $wpdb->rows[] = ['import_job_id' => '5', 'event_id' => '100', 'import_status' => 'applying', 'source_filename' => 'g.csv', 'source_file_hash' => str_repeat('a', 64), 'total_rows' => '2', 'valid_rows' => '2', 'invalid_rows' => '0', 'applied_rows' => '0', 'failed_rows' => '0', 'worker_lease_token' => str_repeat('b', 32), 'worker_lease_expires_at' => '2026-08-16 18:01:00'];
        $this->repository($wpdb)->acquireLease(new EventScope(100), 5, 'worker', str_repeat('b', 32), $this->now(), $this->now()->modify('+60 seconds'));
        self::assertStringContainsString('worker_lease_expires_at <=', $wpdb->queries[0]); self::assertStringContainsString("import_status IN ('validated', 'applying')", $wpdb->queries[0]);
    }

    public function testInvalidValidationUsesSqlNullForNormalizedPayload(): void
    {
        $wpdb = new ImportRepositoryWpdb(); $row = new ImportRowRecord(8, 5, 1, ImportRowStatus::PENDING, ['Name' => '']);
        $this->repository($wpdb)->storeValidation($row, ImportRowStatus::INVALID, null, ['primary_name_invalid'], [], $this->now());
        self::assertStringContainsString('normalized_data = NULL', $wpdb->queries[0]); self::assertStringContainsString('validation_warnings = NULL', $wpdb->queries[0]);
    }

    private function repository(ImportRepositoryWpdb $wpdb): WpdbImportRepository { return new WpdbImportRepository(new WpdbAdapter($wpdb), new WpdbTableNames('wp_')); }
    private function now(): DateTimeImmutable { return new DateTimeImmutable('2026-08-16 18:00:00', new DateTimeZone('UTC')); }
}

final class ImportRepositoryWpdb
{
    public string $prefix = 'wp_'; public string $last_error = ''; public int $last_errno = 0; public int $insert_id = 1; /** @var list<string> */ public array $queries = []; /** @var list<array<string,mixed>|null> */ public array $rows = [];
    public function prepare(string $q, mixed ...$v): string { foreach ($v as $x) { $r = is_int($x) ? (string) $x : "'" . str_replace("'", "''", (string) $x) . "'"; $q = (string) preg_replace('/%[dfs]/', $r, $q, 1); } return $q; }
    public function query(string $q): int { $this->queries[] = $q; return 1; }
    /** @return array<string,mixed>|null */ public function get_row(string $q, string $f): ?array { $this->queries[] = $q; return array_shift($this->rows); }
}
