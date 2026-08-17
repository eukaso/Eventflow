<?php

namespace EventFlow\Tests\Integration;

use EventFlow\Application\Audit\AuditService;
use EventFlow\Application\Attendee\AttendeeService;
use EventFlow\Application\CheckIn\CheckInService;
use EventFlow\Application\Communication\CommunicationService;
use EventFlow\Application\Authorization\AuthorizationException;
use EventFlow\Application\Authorization\AuthorizationService;
use EventFlow\Application\Error\RequestId;
use EventFlow\Application\Event\EventLifecycleService;
use EventFlow\Application\Export\ExportService;
use EventFlow\Application\GuestAccess\GuestAccessService;
use EventFlow\Application\Health\SystemHealthService;
use EventFlow\Application\Idempotency\IdempotencyService;
use EventFlow\Application\Import\ImportService;
use EventFlow\Application\Job\JobException;
use EventFlow\Application\Job\JobRepository;
use EventFlow\Application\Invitation\InvitationService;
use EventFlow\Application\Membership\MembershipService;
use EventFlow\Application\Provider\ProviderService;
use EventFlow\Application\Privacy\PrivacyService;
use EventFlow\Application\Seating\SeatingService;
use EventFlow\Application\Transaction\TransactionManager;
use EventFlow\Bootstrap\BootstrapResult;
use EventFlow\Bootstrap\BootstrapState;
use EventFlow\Bootstrap\Container;
use EventFlow\Infrastructure\Config\Config;
use PHPUnit\Framework\TestCase;

final class FoundationCompositionTest extends TestCase
{
    public function testFoundationGraphComposesAndSharesCompatibleInfrastructure(): void
    {
        $wpdb = new IntegrationWpdb();
        $container = Container::createFoundation(
            new Config('testing', '0.9.0-dev', 6, 'error', false),
            $wpdb,
        );

        self::assertNotNull($container->database);
        self::assertInstanceOf(TransactionManager::class, $container->database->transactions);
        self::assertInstanceOf(AuthorizationService::class, $container->database->authorization);
        self::assertInstanceOf(IdempotencyService::class, $container->database->idempotency);
        self::assertInstanceOf(AuditService::class, $container->database->audit);
        self::assertInstanceOf(EventLifecycleService::class, $container->database->eventLifecycle);
        self::assertInstanceOf(MembershipService::class, $container->database->memberships);
        self::assertInstanceOf(InvitationService::class, $container->database->invitations);
        self::assertInstanceOf(GuestAccessService::class, $container->database->guestAccess);
        self::assertInstanceOf(AttendeeService::class, $container->database->attendees);
        self::assertInstanceOf(ImportService::class, $container->database->imports);
        self::assertInstanceOf(SeatingService::class, $container->database->seating);
        self::assertInstanceOf(CheckInService::class, $container->database->checkIn);
        self::assertInstanceOf(CommunicationService::class, $container->database->communications);
        self::assertInstanceOf(ExportService::class, $container->database->exports);
        self::assertInstanceOf(PrivacyService::class, $container->database->privacy);
        self::assertInstanceOf(ProviderService::class, $container->database->providers);
        self::assertInstanceOf(JobRepository::class, $container->database->jobs);

        $checks = $container->database->readinessChecks;
        $health = new SystemHealthService(
            new BootstrapResult(BootstrapState::READY, true, true),
            $checks,
            $container->services->clock,
            $container->config->pluginVersion,
        );

        self::assertTrue($health->health()->healthy);
        self::assertTrue($health->readiness()->ready);
        $container->database->workerSchema->assertCompatible();

        $response = $container->services->apiErrors->translate(
            new AuthorizationException('insufficient_event_permission'),
            new RequestId('req_0123456789abcdef0123456789abcdef'),
        );
        self::assertSame(403, $response->status);
        self::assertSame('insufficient_event_permission', $response->body['code']);

        self::assertNotEmpty($wpdb->queries);
        self::assertSame([], array_values(array_filter(
            $wpdb->queries,
            static fn (string $query): bool => preg_match('/\b(?:ALTER|CREATE|DELETE|DROP|INSERT|UPDATE)\b/i', $query) === 1,
        )));
    }

    public function testDatabaseFoundationRemainsOptionalForMigrationRequiredMode(): void
    {
        $container = Container::createFoundation(
            new Config('testing', '0.9.0-dev', 6, 'error', false),
        );

        self::assertNull($container->database);
        self::assertSame(
            'internal_error',
            $container->services->errorCodeMapper->publicCode(new \RuntimeException('secret detail')),
        );
    }

    public function testReadinessAndWorkersFailClosedOnTheSameSchemaMismatch(): void
    {
        $container = Container::createFoundation(
            new Config('testing', '0.9.0-dev', 6, 'error', false),
            new IntegrationWpdb('7'),
        );
        self::assertNotNull($container->database);

        $health = new SystemHealthService(
            new BootstrapResult(BootstrapState::READY, true, true),
            $container->database->readinessChecks,
            $container->services->clock,
            $container->config->pluginVersion,
        );
        self::assertFalse($health->readiness()->ready);

        try {
            $container->database->workerSchema->assertCompatible();
            self::fail('Expected the worker schema gate to fail closed.');
        } catch (JobException $exception) {
            self::assertSame('job_worker_schema_incompatible', $exception->safeCode);
        }
    }
}

final class IntegrationWpdb
{
    public string $prefix = 'wp_';
    public string $last_error = '';
    public int $last_errno = 0;

    /** @var list<string> */
    public array $queries = [];

    public function __construct(private readonly string $schemaVersion = '6')
    {
    }

    public function prepare(string $query, mixed ...$values): string
    {
        foreach ($values as $value) {
            $replacement = is_int($value) ? (string) $value : "'" . str_replace("'", "''", (string) $value) . "'";
            $query = (string) preg_replace('/%[dsf]/', $replacement, $query, 1);
        }

        return $query;
    }

    public function esc_like(string $value): string
    {
        return $value;
    }

    public function get_var(string $query): mixed
    {
        $this->queries[] = $query;

        return match (true) {
            str_starts_with($query, 'SHOW TABLES') => 'wp_eventflow_schema_migrations',
            str_starts_with($query, 'SELECT MAX') => $this->schemaVersion,
            $query === 'SELECT 1' => '1',
            default => null,
        };
    }
}
