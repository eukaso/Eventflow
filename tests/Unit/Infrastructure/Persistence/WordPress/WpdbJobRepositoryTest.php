<?php

namespace EventFlow\Tests\Unit\Infrastructure\Persistence\WordPress;

use DateTimeImmutable;
use DateTimeZone;
use EventFlow\Application\Authorization\Capability;
use EventFlow\Application\Job\JobRequest;
use EventFlow\Application\Job\JobStatus;
use EventFlow\Application\Persistence\EventScope;
use EventFlow\Infrastructure\Persistence\WordPress\WpdbAdapter;
use EventFlow\Infrastructure\Persistence\WordPress\WpdbJobRepository;
use EventFlow\Infrastructure\Persistence\WordPress\WpdbTableNames;
use PHPUnit\Framework\TestCase;

final class WpdbJobRepositoryTest extends TestCase
{
    private DateTimeImmutable $now;

    protected function setUp(): void
    {
        $this->now = new DateTimeImmutable('2026-08-16 12:00:00', new DateTimeZone('UTC'));
    }

    public function testEnqueuePersistsJsonContractAuthorityAndOnlyHashedDedupe(): void
    {
        $wpdb = new JobFakeWpdb();
        $wpdb->insert_id = 71;
        $repository = $this->repository($wpdb);
        $request = $this->request('raw-campaign-logical-key');

        $record = $repository->enqueue($request, $this->now);

        self::assertSame(71, $record->jobId);
        self::assertStringContainsString('INSERT INTO wp_eventflow_jobs', $wpdb->queries[0]);
        self::assertStringContainsString('committed_capabilities', $wpdb->queries[0]);
        self::assertStringContainsString('queue_campaign', $wpdb->queries[0]);
        self::assertStringNotContainsString('raw-campaign-logical-key', $wpdb->queries[0]);
        self::assertStringNotContainsString('serialize', strtolower($wpdb->queries[0]));
    }

    public function testClaimUsesPriorityRowLockAndReturnsCommittedAuthority(): void
    {
        $wpdb = new JobFakeWpdb();
        $wpdb->rows = [$this->row(JobStatus::PENDING, 1)];
        $repository = $this->repository($wpdb);

        $record = $repository->claimNext(
            'worker-a',
            str_repeat('a', 32),
            $this->now,
            $this->now->modify('+60 seconds'),
        );

        self::assertNotNull($record);
        self::assertSame(JobStatus::RUNNING, $record->status);
        self::assertSame(2, $record->attemptCount);
        self::assertSame(Capability::QUEUE_CAMPAIGN, $record->committedCapabilities[0]);
        self::assertStringContainsString('ORDER BY priority ASC', $wpdb->queries[0]);
        self::assertStringContainsString('FOR UPDATE SKIP LOCKED', $wpdb->queries[0]);
        self::assertStringContainsString('attempt_count = attempt_count + 1', $wpdb->queries[1]);
    }

    public function testLogicalDedupeKeyCannotSilentlyAliasDifferentPayload(): void
    {
        $wpdb = new JobFakeWpdb();
        $wpdb->queryResults = [false];
        $wpdb->rows = [$this->row(JobStatus::PENDING, 0, 99)];
        $repository = $this->repository($wpdb);

        $this->expectException(\EventFlow\Infrastructure\Persistence\PersistenceException::class);
        $this->expectExceptionMessage('job_dedupe_conflict');
        $repository->enqueue($this->request('same-logical-key'), $this->now);
    }

    public function testCompletionAndHeartbeatRequireTheCurrentLease(): void
    {
        $wpdb = new JobFakeWpdb();
        $repository = $this->repository($wpdb);
        $token = str_repeat('b', 32);

        $repository->heartbeat(71, $token, $this->now, $this->now->modify('+60 seconds'));
        $repository->complete(71, $token, $this->now);

        self::assertStringContainsString('lease_expires_at', $wpdb->queries[0]);
        self::assertStringContainsString("lease_token = '{$token}'", $wpdb->queries[0]);
        self::assertStringContainsString("job_status = 'completed'", $wpdb->queries[1]);
        self::assertStringContainsString("lease_token = '{$token}'", $wpdb->queries[1]);
    }

    public function testReconciliationDeadLettersExhaustedAndRecoversExpiredLeases(): void
    {
        $wpdb = new JobFakeWpdb();
        $wpdb->queryResults = [2, 3];
        $wpdb->value = '1';
        $result = $this->repository($wpdb)->reconcile($this->now);

        self::assertSame(3, $result->recovered);
        self::assertSame(2, $result->deadLettered);
        self::assertTrue($result->runnableWorkExists);
        self::assertStringContainsString("job_status = 'dead_letter'", $wpdb->queries[0]);
        self::assertStringContainsString('attempt_count >= max_attempts', $wpdb->queries[0]);
        self::assertStringContainsString("last_error_code = 'job_lease_expired'", $wpdb->queries[1]);
        self::assertStringContainsString('SELECT EXISTS', $wpdb->queries[2]);
    }

    private function request(?string $logicalKey): JobRequest
    {
        return JobRequest::create(
            new EventScope(10),
            'campaign.dispatch',
            1,
            ['campaign_id' => 44],
            [Capability::QUEUE_CAMPAIGN],
            $this->now,
            10,
            3,
            $logicalKey,
        );
    }

    /** @return array<string, mixed> */
    private function row(JobStatus $status, int $attemptCount, int $campaignId = 44): array
    {
        return [
            'job_id' => '71',
            'event_id' => '10',
            'job_type' => 'campaign.dispatch',
            'payload_version' => '1',
            'payload' => json_encode([
                'data' => ['campaign_id' => $campaignId],
                'committed_capabilities' => ['queue_campaign'],
            ], JSON_THROW_ON_ERROR),
            'job_status' => $status->value,
            'priority' => '10',
            'attempt_count' => (string) $attemptCount,
            'max_attempts' => '3',
        ];
    }

    private function repository(JobFakeWpdb $wpdb): WpdbJobRepository
    {
        $database = new WpdbAdapter($wpdb);
        return new WpdbJobRepository($database, new WpdbTableNames($database->tablePrefix()));
    }
}

final class JobFakeWpdb
{
    public string $prefix = 'wp_';
    public string $last_error = '';
    public int $last_errno = 0;
    public int $insert_id = 0;
    /** @var list<array<string, mixed>|null> */
    public array $rows = [];
    /** @var list<string> */
    public array $queries = [];
    /** @var list<int|false> */
    public array $queryResults = [];
    public mixed $value = null;

    public function prepare(string $query, mixed ...$values): string
    {
        return vsprintf(str_replace(['%s', '%d'], ["'%s'", '%d'], $query), $values);
    }

    /** @return array<string, mixed>|null */
    public function get_row(string $query, string $output): ?array
    {
        $this->queries[] = $query;
        return array_shift($this->rows);
    }

    public function get_var(string $query): mixed
    {
        $this->queries[] = $query;
        return $this->value;
    }

    public function query(string $query): int|false
    {
        $this->queries[] = $query;
        $result = $this->queryResults === [] ? 1 : array_shift($this->queryResults);
        if ($result === false) {
            $this->last_errno = 1062;
        }
        return $result;
    }
}
