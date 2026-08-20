<?php

namespace EventFlow\Bootstrap;

use EventFlow\Application\Audit\AuditCanonicalizer;
use EventFlow\Application\Audit\AuditPayloadRedactor;
use EventFlow\Application\Audit\AuditService;
use EventFlow\Application\Attendee\AttendeeService;
use EventFlow\Application\Attendee\AttendeeQueryService;
use EventFlow\Application\CheckIn\CheckInService;
use EventFlow\Application\Communication\CommunicationService;
use EventFlow\Application\Communication\CampaignAccessService;
use EventFlow\Application\Communication\MessageAccessService;
use EventFlow\Application\Communication\TemplateAccessService;
use EventFlow\Application\Communication\TemplateRenderer;
use EventFlow\Application\Authorization\AuthorizationService;
use EventFlow\Application\Authorization\RoleCapabilityPolicy;
use EventFlow\Application\Event\EventAccessService;
use EventFlow\Application\Event\EventLifecycleService;
use EventFlow\Application\EventConfiguration\EventConfigurationService;
use EventFlow\Application\Export\ExportService;
use EventFlow\Application\Export\ExportAccessService;
use EventFlow\Application\Export\ExportArtifactReader;
use EventFlow\Application\Health\ReadinessCheck;
use EventFlow\Application\Health\PrivacyReconciliationReadinessCheck;
use EventFlow\Application\GuestAccess\GuestAccessService;
use EventFlow\Application\GuestAccess\GuestSessionAccessService;
use EventFlow\Application\Idempotency\CanonicalRequestHasher;
use EventFlow\Application\Idempotency\IdempotencyService;
use EventFlow\Application\Import\ImportNormalizer;
use EventFlow\Application\Import\ImportService;
use EventFlow\Application\Import\ImportAdministrationService;
use EventFlow\Application\Job\JobRepository;
use EventFlow\Application\Job\WorkerSchemaGate;
use EventFlow\Application\Invitation\InvitationService;
use EventFlow\Application\Invitation\InvitationAccessService;
use EventFlow\Application\Membership\MembershipService;
use EventFlow\Application\Membership\MembershipQueryService;
use EventFlow\Application\Migration\MigrationRepository;
use EventFlow\Application\Observability\DiagnosticService;
use EventFlow\Application\Provider\ProviderRegistry;
use EventFlow\Application\Provider\ProviderService;
use EventFlow\Application\Privacy\PrivacyService;
use EventFlow\Application\Seating\{SeatingGroupMoveService, SeatingRecommendationService, SeatingResourceService, SeatingService};
use EventFlow\Application\Security\CredentialDigester;
use EventFlow\Application\Transaction\TransactionManager;
use EventFlow\Application\Venue\VenueService;
use EventFlow\Infrastructure\Config\Config;
use EventFlow\Infrastructure\Health\SchemaReadinessCheck;
use EventFlow\Infrastructure\Health\WpdbConnectionReadinessCheck;
use EventFlow\Infrastructure\Export\WordPressProtectedExportStorage;
use EventFlow\Infrastructure\Export\WpdbExportDataSource;
use EventFlow\Infrastructure\Job\MigrationWorkerSchemaGate;
use EventFlow\Infrastructure\Import\NativeTabularSourceParser;
use EventFlow\Infrastructure\Persistence\WordPress\WpdbAdapter;
use EventFlow\Infrastructure\Persistence\WordPress\WpdbAttendeeRepository;
use EventFlow\Infrastructure\Persistence\WordPress\WpdbAttendeeQueryRepository;
use EventFlow\Infrastructure\Persistence\WordPress\WpdbCheckInRepository;
use EventFlow\Infrastructure\Persistence\WordPress\WpdbCommunicationRepository;
use EventFlow\Infrastructure\Persistence\WordPress\WpdbAuditRepository;
use EventFlow\Infrastructure\Persistence\WordPress\WpdbEventLifecycleRepository;
use EventFlow\Infrastructure\Persistence\WordPress\WpdbEventConfigurationRepository;
use EventFlow\Infrastructure\Persistence\WordPress\WpdbEventQueryRepository;
use EventFlow\Infrastructure\Persistence\WordPress\WpdbExportRepository;
use EventFlow\Infrastructure\Persistence\WordPress\WpdbIdempotencyRepository;
use EventFlow\Infrastructure\Persistence\WordPress\WpdbGuestAccessRepository;
use EventFlow\Infrastructure\Persistence\WordPress\WpdbGuestSessionAccessRepository;
use EventFlow\Infrastructure\Persistence\WordPress\WpdbInvitationRepository;
use EventFlow\Infrastructure\Persistence\WordPress\WpdbInvitationAccessRepository;
use EventFlow\Infrastructure\Persistence\WordPress\WpdbImportRepository;
use EventFlow\Infrastructure\Persistence\WordPress\WpdbJobRepository;
use EventFlow\Infrastructure\Persistence\WordPress\WpdbMembershipReader;
use EventFlow\Infrastructure\Persistence\WordPress\WpdbMembershipQueryRepository;
use EventFlow\Infrastructure\Persistence\WordPress\WpdbMembershipRepository;
use EventFlow\Infrastructure\Persistence\WordPress\WpdbProviderRepository;
use EventFlow\Infrastructure\Persistence\WordPress\WpdbPrivacyRepository;
use EventFlow\Infrastructure\Provider\WordPressTransientProviderCircuitBreaker;
use EventFlow\Infrastructure\Persistence\WordPress\WpdbSchemaMetadataRepository;
use EventFlow\Infrastructure\Persistence\WordPress\WpdbSeatingRepository;
use EventFlow\Infrastructure\Persistence\WordPress\WpdbSeatingRecommendationRepository;
use EventFlow\Infrastructure\Persistence\WordPress\WpdbTableNames;
use EventFlow\Infrastructure\Persistence\WordPress\WpdbVenueRepository;
use EventFlow\Infrastructure\Observability\{ReadinessDiagnosticSource, RuntimeDiagnosticSource, SchemaDiagnosticSource};
use EventFlow\Infrastructure\Transaction\WpdbTransactionManager;
use EventFlow\Infrastructure\WordPress\WordPressGlobalRecoveryAuthority;
use EventFlow\Infrastructure\WordPress\WordPressEventCreationAuthority;
use EventFlow\Infrastructure\WordPress\WordPressVenueAuthority;
use EventFlow\Infrastructure\WordPress\WpdbEventCapabilityGate;

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
        public EventAccessService $eventAccess,
        public VenueService $venues,
        public EventConfigurationService $eventConfigurations,
        public MembershipService $memberships,
        public MembershipQueryService $membershipQueries,
        public InvitationService $invitations,
        public InvitationAccessService $invitationAccess,
        public GuestAccessService $guestAccess,
        public GuestSessionAccessService $guestSessionAccess,
        public AttendeeService $attendees,
        public AttendeeQueryService $attendeeQueries,
        public ImportService $imports,
        public ImportAdministrationService $importAdministration,
        public SeatingService $seating,
        public SeatingResourceService $seatingResources,
        public SeatingRecommendationService $seatingRecommendations,
        public SeatingGroupMoveService $seatingGroupMoves,
        public CheckInService $checkIn,
        public CommunicationService $communications,
        public TemplateAccessService $templateAccess,
        public CampaignAccessService $campaignAccess,
        public MessageAccessService $messageAccess,
        public ExportService $exports,
        public ExportAccessService $exportAccess,
        public ExportArtifactReader $exportArtifacts,
        public PrivacyService $privacy,
        public ProviderService $providers,
        public JobRepository $jobs,
        public WorkerSchemaGate $workerSchema,
        public DiagnosticService $diagnostics,
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
            new WpdbEventCapabilityGate($database, $tableNames),
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
        $jobRepository = new WpdbJobRepository($database, $tableNames);
        $seatingRepository = new WpdbSeatingRepository($database, $tableNames);
        $communicationRepository = new WpdbCommunicationRepository($database, $tableNames);
        $templateRenderer = new TemplateRenderer();
        $exportStorage = new WordPressProtectedExportStorage($shared->random);
        $exportRepository = new WpdbExportRepository($database, $tableNames);
        $privacy = new PrivacyService(
            new WpdbPrivacyRepository($database, $tableNames),
            $exportStorage,
            $jobRepository,
            $authorization,
            $idempotency,
            $audit,
            $shared->clock,
            $transactions,
        );
        $invitationService = new InvitationService(
            $invitationRepository,
            $authorization,
            $idempotency,
            $audit,
            $shared->clock,
            $shared->random,
            $credentialDigester,
        );
        $readinessChecks = [
            new WpdbConnectionReadinessCheck($database),
            new SchemaReadinessCheck(
                $migrations,
                $shared->schemaCompatibility,
                $config->expectedSchemaVersion,
            ),
            new PrivacyReconciliationReadinessCheck($privacy),
        ];
        $diagnostics = new DiagnosticService(
            $authorization,
            $shared->observabilityRedactor,
            $shared->clock,
            [
                new RuntimeDiagnosticSource($config),
                new SchemaDiagnosticSource($migrations, $config->expectedSchemaVersion),
                new ReadinessDiagnosticSource($readinessChecks),
            ],
        );
        $seatingService = new SeatingService(
            $seatingRepository,
            $authorization,
            $idempotency,
            $audit,
            $shared->clock,
            $transactions,
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
            eventAccess: new EventAccessService(
                $eventRepository,
                new WpdbEventQueryRepository($database, $tableNames),
                $authorization,
                $idempotency,
                $audit,
                $shared->clock,
            ),
            venues: new VenueService(
                new WpdbVenueRepository($database, $tableNames),
                new WordPressVenueAuthority(),
                $idempotency,
                $audit,
                $shared->clock,
            ),
            eventConfigurations: new EventConfigurationService(
                new WpdbEventConfigurationRepository($database, $tableNames),
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
            membershipQueries: new MembershipQueryService(
                new WpdbMembershipQueryRepository($database, $tableNames),
                $authorization,
            ),
            invitations: $invitationService,
            invitationAccess: new InvitationAccessService(
                new WpdbInvitationAccessRepository($database, $tableNames),
                $authorization,
                $idempotency,
                $audit,
                $shared->clock,
            ),
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
            guestSessionAccess: new GuestSessionAccessService(
                new WpdbGuestSessionAccessRepository($database, $tableNames),
                $authorization,
                $shared->clock,
            ),
            attendees: new AttendeeService(
                $attendeeRepository,
                $authorization,
                $idempotency,
                $audit,
                $shared->clock,
            ),
            attendeeQueries: new AttendeeQueryService(
                new WpdbAttendeeQueryRepository($database, $tableNames),
                $authorization,
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
            importAdministration: new ImportAdministrationService(
                $importRepository,
                $jobRepository,
                $authorization,
                $idempotency,
                $audit,
                $shared->clock,
            ),
            seating: $seatingService,
            seatingResources: new SeatingResourceService(
                $seatingRepository,
                $authorization,
                $idempotency,
                $audit,
                $shared->clock,
            ),
            seatingRecommendations: new SeatingRecommendationService(
                $seatingService,
                new WpdbSeatingRecommendationRepository($database, $tableNames),
                $authorization,
                $idempotency,
                $audit,
                $shared->clock,
                $transactions,
            ),
            seatingGroupMoves: new SeatingGroupMoveService(
                $seatingRepository,
                $authorization,
                $idempotency,
                $audit,
                $shared->clock,
            ),
            checkIn: new CheckInService(
                new WpdbCheckInRepository($database, $tableNames),
                $authorization,
                $idempotency,
                $audit,
                $shared->clock,
                $shared->random,
            ),
            communications: new CommunicationService(
                $communicationRepository,
                $authorization,
                $idempotency,
                $audit,
                $shared->clock,
                $templateRenderer,
            ),
            templateAccess: new TemplateAccessService(
                $communicationRepository,
                $authorization,
                $idempotency,
                $audit,
                $shared->clock,
                $templateRenderer,
            ),
            campaignAccess: new CampaignAccessService($communicationRepository,$authorization,$idempotency,$audit,$shared->clock),
            messageAccess: new MessageAccessService($communicationRepository,$jobRepository,$authorization,$idempotency,$audit,$shared->clock),
            exports: new ExportService(
                $exportRepository,
                new WpdbExportDataSource($database, $tableNames),
                $exportStorage,
                $jobRepository,
                $authorization,
                $idempotency,
                $audit,
                $shared->clock,
                $transactions,
            ),
            exportAccess: new ExportAccessService($exportRepository, $authorization),
            exportArtifacts: $exportStorage,
            privacy: $privacy,
            providers: new ProviderService(
                new WpdbProviderRepository($database, $tableNames),
                new ProviderRegistry(),
                $jobRepository,
                $shared->clock,
                new WordPressTransientProviderCircuitBreaker(),
            ),
            jobs: $jobRepository,
            workerSchema: new MigrationWorkerSchemaGate(
                $migrations,
                $shared->schemaCompatibility,
                $config->expectedSchemaVersion,
            ),
            diagnostics: $diagnostics,
            readinessChecks: $readinessChecks,
        );
    }
}
