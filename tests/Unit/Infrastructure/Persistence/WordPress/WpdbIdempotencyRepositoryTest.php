<?php

namespace EventFlow\Tests\Unit\Infrastructure\Persistence\WordPress;

use DateTimeImmutable;
use DateTimeZone;
use EventFlow\Application\Authorization\PrincipalContext;
use EventFlow\Application\Idempotency\CanonicalRequestHasher;
use EventFlow\Application\Idempotency\IdempotencyClaimState;
use EventFlow\Application\Idempotency\IdempotencyRequest;
use EventFlow\Application\Idempotency\IdempotencyResultReference;
use EventFlow\Application\Persistence\EventScope;
use EventFlow\Infrastructure\Persistence\WordPress\WpdbAdapter;
use EventFlow\Infrastructure\Persistence\WordPress\WpdbIdempotencyRepository;
use EventFlow\Infrastructure\Persistence\WordPress\WpdbTableNames;
use PHPUnit\Framework\TestCase;

final class WpdbIdempotencyRepositoryTest extends TestCase
{
    private DateTimeImmutable $now;
    private EventScope $event;

    protected function setUp(): void
    {
        $this->now = new DateTimeImmutable('2026-08-15 12:00:00', new DateTimeZone('UTC'));
        $this->event = new EventScope(10);
    }

    public function testNewClaimPersistsOnlyHashedScopeAndKey(): void
    {
        $wpdb = new IdempotencyFakeWpdb();
        $wpdb->insert_id = 71;
        $repository = $this->repository($wpdb);
        $request = $this->request('raw-client-key-12345');

        $claim = $repository->claim(
            $request,
            'aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa',
            $this->now,
            $this->now->modify('+30 seconds'),
            $this->now->modify('+1 day'),
        );

        self::assertSame(IdempotencyClaimState::ACQUIRED, $claim->state);
        self::assertSame(71, $claim->record->recordId);
        self::assertStringContainsString('INSERT INTO wp_eventflow_idempotency_records', $wpdb->queries[1]);
        self::assertStringNotContainsString('raw-client-key-12345', $wpdb->queries[1]);
        self::assertStringNotContainsString('wp_user:7', $wpdb->queries[1]);
    }

    public function testCompletedAndConflictingClaimsAreDistinguishedUnderRowLock(): void
    {
        $request = $this->request('raw-client-key-12345');
        $wpdb = new IdempotencyFakeWpdb();
        $wpdb->row = $this->completedRow($request->requestFingerprint);
        $repository = $this->repository($wpdb);

        $replay = $repository->claim(
            $request,
            'aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa',
            $this->now,
            $this->now->modify('+30 seconds'),
            $this->now->modify('+1 day'),
        );

        self::assertSame(IdempotencyClaimState::REPLAY, $replay->state);
        self::assertSame(44, $replay->record->resultReference?->entityId);
        self::assertStringContainsString('FOR UPDATE', $wpdb->queries[0]);

        $different = $this->request('raw-client-key-12345', ['name' => 'Different']);
        $conflict = $repository->claim(
            $different,
            'bbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbb',
            $this->now,
            $this->now->modify('+30 seconds'),
            $this->now->modify('+1 day'),
        );
        self::assertSame(IdempotencyClaimState::CONFLICT, $conflict->state);
    }

    public function testExpiredRecordCanBeSafelyReinitializedForNewRequest(): void
    {
        $request = $this->request('raw-client-key-12345', ['name' => 'New request']);
        $wpdb = new IdempotencyFakeWpdb();
        $wpdb->row = $this->completedRow(str_repeat('0', 64));
        $wpdb->row['expires_at'] = '2026-08-15 11:59:59';
        $repository = $this->repository($wpdb);

        $claim = $repository->claim(
            $request,
            'cccccccccccccccccccccccccccccccc',
            $this->now,
            $this->now->modify('+30 seconds'),
            $this->now->modify('+1 day'),
        );

        self::assertSame(IdempotencyClaimState::ACQUIRED, $claim->state);
        self::assertSame($request->requestFingerprint, $claim->record->requestFingerprint);
        self::assertStringContainsString('result_entity_type = NULL', $wpdb->queries[1]);
        self::assertStringContainsString('sensitive_result = 0', $wpdb->queries[1]);
    }

    public function testCompletionRequiresMatchingLeaseAndPersistsOnlyReferenceMetadata(): void
    {
        $wpdb = new IdempotencyFakeWpdb();
        $repository = $this->repository($wpdb);
        $repository->complete(
            71,
            'aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa',
            new IdempotencyResultReference('invitation', 81, 201),
            true,
            $this->now,
        );

        self::assertStringContainsString('sensitive_result = 1', $wpdb->queries[0]);
        self::assertStringContainsString("result_entity_type = 'invitation'", $wpdb->queries[0]);
        self::assertStringContainsString('result_entity_id = 81', $wpdb->queries[0]);
        self::assertStringContainsString("execution_lease_token = 'aaaaaaaa", $wpdb->queries[0]);
    }

    private function request(string $key, array $payload = ['name' => 'Ada']): IdempotencyRequest
    {
        return IdempotencyRequest::create(
            PrincipalContext::wordpressUser(7),
            $this->event,
            'attendee.create',
            $key,
            $payload,
            new CanonicalRequestHasher(),
        );
    }

    /** @return array<string, mixed> */
    private function completedRow(string $fingerprint): array
    {
        return [
            'idempotency_record_id' => '71',
            'request_fingerprint' => $fingerprint,
            'execution_status' => 'completed',
            'execution_lease_expires_at' => null,
            'result_entity_type' => 'attendee',
            'result_entity_id' => '44',
            'response_status_code' => '201',
            'sensitive_result' => '0',
            'expires_at' => '2026-08-16 12:00:00',
        ];
    }

    private function repository(IdempotencyFakeWpdb $wpdb): WpdbIdempotencyRepository
    {
        $database = new WpdbAdapter($wpdb);
        return new WpdbIdempotencyRepository($database, new WpdbTableNames($database->tablePrefix()));
    }
}

final class IdempotencyFakeWpdb
{
    public string $prefix = 'wp_';
    public string $last_error = '';
    public int $last_errno = 0;
    public int $insert_id = 0;
    /** @var array<string, mixed>|null */
    public ?array $row = null;
    /** @var list<string> */
    public array $queries = [];

    public function prepare(string $query, mixed ...$values): string
    {
        return vsprintf(str_replace(['%s', '%d'], ["'%s'", '%d'], $query), $values);
    }

    /** @return array<string, mixed>|null */
    public function get_row(string $query, string $output): ?array
    {
        $this->queries[] = $query;
        return $this->row;
    }

    public function query(string $query): int|false
    {
        $this->queries[] = $query;
        return 1;
    }
}
