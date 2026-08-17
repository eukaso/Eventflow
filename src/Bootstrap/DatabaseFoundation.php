<?php

namespace EventFlow\Bootstrap;

use EventFlow\Application\Audit\AuditCanonicalizer;
use EventFlow\Application\Audit\AuditPayloadRedactor;
use EventFlow\Application\Audit\AuditService;
use EventFlow\Application\Attendee\AttendeeService;
use EventFlow\Application\CheckIn\CheckInService;
use EventFlow\Application\Authorization\AuthorizationService;
use EventFlow\Application\Authorization\RoleCapabilityPolicy;
use EventFlow\Application\Event\EventLifecycleService;
use EventFlow\Application\Health\ReadinessCheck;
use EventFlow\Application\GuestAccess\GuestAccessService;
use EventFlow\Application\Idempotency\CanonicalRequestHasher;
use EventFlow\Application\Idempotency\IdempotencyService;
use EventFlow\Application\Import\ImportNormalizer;
use EventFlow\Application\Import\ImportService;
use EventFlow\Application\Job\JobRepository;
use EventFlow\Application\Job\WorkerSchemaGate;
use EventFlow\Application\Invitation\InvitationService;
use EventFlow\Application\Membership\MembershipService;
use EventFlow\Application\Migration\MigrationRepository;
use EventFlow\Application\Seating\SeatingService;
use EventFlow\Application\Security\CredentialDigester;
use EventFlow\Application\Transaction\TransactionManager;
use EventFlow\Infrastructure\Config\Config;
use EventFlow\Infrastructure\Health\SchemaReadinessCheck;
use EventFlow\Infrastructure\Health\WpdbConnectionReadinessCheck;
use EventFlow\Infrastructure\Job\MigrationWorkerSchemaGate;
use EventFlow\Infrastructure\Import\NativeTabularSourceParser;
use EventFlow\Infrastructure\Persistence\WordPress\WpdbAdapter;
use EventFlow\Infrastructure\Persistence\WordPress\WpdbAttendeeRepository;
use EventFlow\Infrastructure\Persistence\WordPress\WpdbCheckInRepository;
use EventFlow\Infrastructure\Persistence\WordPress\WpdbAuditRepository;
use EventFlow\Infrastructure\Persistence\WordPress\WpdbEventLifecycleRepository;
use EventFlow\Infrastructure\Persistence\WordPress\WpdbIdempotencyRepository;
use EventFlow\Infrastructure\Persistence\WordPress\WpdbGuestAccessRepository;
use EventFlow\Infrastructure\Persistence\WordPress\WpdbInvitationRepository;
use EventFlow\Infrastructure\Persistence\WordPress\WpdbImportRepository;
use EventFlow\Infrastructure\Persistence\WordPress\WpdbJobRepository;
use EventFlow\Infrastructure\Persistence\WordPress\WpdbMembershipReader;
use EventFlow\Infrastructure\Persistence\WordPress\WpdbMembershipRepository;
use EventFlow\Infrastructure\Persistence\WordPress\WpdbSchemaMetadataRepository;
use EventFlow\Infrastructure\Persistence\WordPress\WpdbSeatingRepository;
use EventFlow\Infrastructure\Persistence\WordPress\WpdbTableNames;
use EventFlow\Infrastructure\Transaction\WpdbTransactionManager;
use EventFlow\Infrastructure\WordPress\WordPressGlobalRecoveryAuthority;
use EventFlow\Infrastructure\WordPress\WordPressEventCreationAuthority;

final readonly class DatabaseFoundation
{
    /**
     * @param list<ReadinessCheck> $readinessChecks
     */
    private function __construct(
        public WpdbAdapter $database,
        public WpdbTableNames $tableNames,
        public MigrationRepository $migrations,
        public TransactionManager $transactions,
        public AuthorizationService $authorization,
        public IdempotencyService $idempotency,
        public AuditService $audit,
        public EventLifecycleService $eventLifecycle,
        public MembershipService $memberships,
        public InvitationService $invitations,
        public GuestAccessService $guestAccess,
        public AttendeeService $attendees,
        public ImportService $imports,
        public SeatingService $seating,
        public CheckInService $checkIn,
        public JobRepository $jobs,
        public WorkerSchemaGate $workerSchema,
        public array $readinessChecks,
    ) {
    }

    public static function create(object $wpdb, Config $config, FoundationServices $shared): self
    {
        $database = new WpdbAdapter($wpdb);
        $tableNames = new WpdbTableNames($database->tablePrefix());
        $migrations = new WpdbSchemaMetadataRepository($database, $tableNames);
        $transactions = new WpdbTransactionManager($database);
        $authorization = new AuthorizationService(
            new WpdbMembershipReader($database, $tableNames),
            new RoleCapabilityPolicy(),
            $shared->clock,
            new WordPressGlobalRecoveryAuthority(),
        );
        $idempotency = new IdempotencyService(
            new WpdbIdempotencyRepository($database, $tableNames),
            $transactions,
            $shared->clock,
            $shared->random,
            new CanonicalRequestHasher(),
        );
        $audit = new AuditService(
            new WpdbAuditRepository($database, $tableNames),
            $transactions,
            $shared->clock,
            new AuditPayloadRedactor(),
            new AuditCanonicalizer(),
        );
        $eventRepository = new WpdbEventLifecycleRepository($database, $tableNames);
        $membershipRepository = new WpdbMembershipRepository($database, $tableNames);
        $invitationRepository = new WpdbInvitationRepository($database, $tableNames);
        $guestAccessRepository = new WpdbGuestAccessRepository($database, $tableNames);
        $credentialDigester = new CredentialDigester();
        $attendeeRepository = new WpdbAttendeeRepository($database, $tableNames);
        $importRepository = new WpdbImportRepository($database, $tableNames);
        $invitationService = new InvitationService(
            $invitationRepository,
            $authorization,
            $idempotency,
            $audit,
            $shared->clock,
            $shared->random,
            $credentialDigester,
        );

        return new self(
            database: $database,
            tableNames: $tableNames,
            migrations: $migrations,
            transactions: $transactions,
            authorization: $authorization,
            idempotency: $idempotency,
            audit: $audit,
            eventLifecycle: new EventLifecycleService(
                $eventRepository,
                new WordPressEventCreationAuthority(),
                $authorization,
                $idempotency,
                $audit,
                $shared->clock,
            ),
            memberships: new MembershipService(
                $membershipRepository,
                $authorization,
                $idempotency,
                $audit,
                $shared->clock,
            ),
            invitations: $invitationService,
            guestAccess: new GuestAccessService(
                $guestAccessRepository,
                $invitationRepository,
                $authorization,
                $idempotency,
                $audit,
                $shared->clock,
                $shared->random,
                $credentialDigester,
                $transactions,
            ),
            attendees: new AttendeeService(
                $attendeeRepository,
                $authorization,
                $idempotency,
                $audit,
                $shared->clock,
            ),
            imports: new ImportService(
                $importRepository,
                new NativeTabularSourceParser(),
                new ImportNormalizer(),
                $invitationService,
                $authorization,
                $idempotency,
                $audit,
                $shared->clock,
                $shared->random,
                $transactions,
            ),
            seating: new SeatingService(
                new WpdbSeatingRepository($database, $tableNames),
                $authorization,
                $idempotency,
                $audit,
                $shared->clock,
                $transactions,
            ),
            checkIn: new CheckInService(
                new WpdbCheckInRepository($database, $tableNames),
                $authorization,
                $idempotency,
                $audit,
                $shared->clock,
                $shared->random,
            ),
            jobs: new WpdbJobRepository($database, $tableNames),
            workerSchema: new MigrationWorkerSchemaGate(
                $migrations,
                $shared->schemaCompatibility,
                $config->expectedSchemaVersion,
            ),
            readinessChecks: [
                new WpdbConnectionReadinessCheck($database),
                new SchemaReadinessCheck(
                    $migrations,
                    $shared->schemaCompatibility,
                    $config->expectedSchemaVersion,
                ),
            ],
        );
    }
}
