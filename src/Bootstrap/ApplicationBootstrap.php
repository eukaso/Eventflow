<?php

namespace EventFlow\Bootstrap;

use EventFlow\Application\Health\SystemHealthService;
use EventFlow\Infrastructure\Config\ConfigException;
use EventFlow\Infrastructure\Config\ConfigLoader;
use EventFlow\Infrastructure\Health\BootstrapReadinessCheck;
use EventFlow\Presentation\Api\{AttendeeController, AttendeePresenter, AttendeeQueryController, AttendeeQueryRequestMapper, AttendeeQueryRouteRegistrar, AttendeeRequestMapper, AttendeeRouteRegistrar, CampaignAccessController, CampaignAccessPresenter, CampaignAccessRequestMapper, CampaignAccessRouteRegistrar, CampaignController, CampaignPresenter, CampaignRequestMapper, CampaignRouteRegistrar, CheckInController, CheckInPresenter, CheckInRequestMapper, CheckInRouteRegistrar, EventController, EventPresenter, EventRequestMapper, EventRouteRegistrar, GuestBootstrapController, GuestBootstrapRequestMapper, GuestBootstrapRouteRegistrar, GuestRequestContextFactory, GuestSessionAccessController, GuestSessionAccessPresenter, GuestSessionAccessRequestMapper, GuestSessionAccessRouteRegistrar, GuestSessionPresenter, ImportController, ImportPresenter, ImportRequestMapper, ImportRouteRegistrar, InvitationAccessController, InvitationAccessRequestMapper, InvitationAccessRouteRegistrar, InvitationController, InvitationPresenter, InvitationRequestMapper, InvitationRouteRegistrar, MembershipController, MembershipPresenter, MembershipQueryController, MembershipQueryRequestMapper, MembershipQueryRouteRegistrar, MembershipRequestMapper, MembershipRouteRegistrar, ProviderWebhookController, ProviderWebhookPresenter, ProviderWebhookRequestMapper, ProviderWebhookRouteRegistrar, RsvpController, RsvpPresenter, RsvpRequestMapper, RsvpRouteRegistrar, SeatingGroupMoveController, SeatingGroupMovePresenter, SeatingGroupMoveRequestMapper, SeatingGroupMoveRouteRegistrar, SeatingPlanningController, SeatingPlanningPresenter, SeatingPlanningRequestMapper, SeatingPlanningRouteRegistrar, SeatingPreparationController, SeatingPreparationPresenter, SeatingPreparationRequestMapper, SeatingPreparationRouteRegistrar, SeatingRecommendationController, SeatingRecommendationPresenter, SeatingRecommendationRequestMapper, SeatingRecommendationRouteRegistrar, SeatingResourceController, SeatingResourcePresenter, SeatingResourceRequestMapper, SeatingResourceRouteRegistrar, SystemRouteRegistrar, SystemStatusController, SystemStatusPresenter, TemplateAccessController, TemplateAccessPresenter, TemplateAccessRequestMapper, TemplateAccessRouteRegistrar, TemplateController, TemplatePresenter, TemplateRequestMapper, TemplateRouteRegistrar};
use EventFlow\Presentation\Api\{EventConfigurationController, EventConfigurationPresenter, EventConfigurationRequestMapper, EventConfigurationRouteRegistrar, VenueController, VenuePresenter, VenueRequestMapper, VenueRouteRegistrar};
use EventFlow\Presentation\Api\{MessageAccessController, MessageAccessPresenter, MessageAccessRequestMapper, MessageAccessRouteRegistrar};
use EventFlow\Presentation\Api\{ImportAdministrationController, ImportAdministrationPresenter, ImportAdministrationRequestMapper, ImportAdministrationRouteRegistrar, ImportUploadController};
use EventFlow\Infrastructure\Import\HardenedImportUploadGuard;
use EventFlow\Presentation\WordPress\{WordPressPublicBootstrapRateLimiter, WordPressRestRequestMapper, WordPressRestRouteHooks, WordPressRestRouteRegistry};
use Throwable;

final class ApplicationBootstrap
{
    private static ?BootstrapResult $result = null;

    public static function boot(): BootstrapResult
    {
        if (self::$result !== null) {
            return self::$result;
        }

        try {
            $runtimeErrors = (new RuntimeValidator())->validate();

            if ($runtimeErrors !== []) {
                return self::$result = new BootstrapResult(
                    BootstrapState::UNSUPPORTED_RUNTIME,
                    false,
                    false,
                    $runtimeErrors,
                );
            }

            $config = (new ConfigLoader())->load();
            global $wpdb;
            $container = Container::createFoundation($config, is_object($wpdb) ? $wpdb : null);

            $installedSchemaVersion = $container->database?->migrations->currentSchemaVersion();

            $compatibility = $container
                ->services
                ->schemaCompatibility
                ->check($config->expectedSchemaVersion, $installedSchemaVersion);

            $result = match ($compatibility) {
                SchemaCompatibility::COMPATIBLE => self::registerFullMode(),
                SchemaCompatibility::MIGRATION_REQUIRED => self::registerMinimalMode(
                    BootstrapState::MIGRATION_REQUIRED,
                    ['schema_migration_required']
                ),
                SchemaCompatibility::APPLICATION_TOO_OLD => self::registerMinimalMode(
                    BootstrapState::INCOMPATIBLE_SCHEMA,
                    ['application_schema_incompatible']
                ),
                SchemaCompatibility::UNKNOWN => self::registerMinimalMode(
                    BootstrapState::FAILED,
                    ['schema_compatibility_unknown']
                ),
            };
            self::$result = $result;
            self::registerRoutes($container, $result);
            return $result;
        } catch (ConfigException $e) {
            return self::$result = new BootstrapResult(
                BootstrapState::INVALID_CONFIGURATION,
                false,
                false,
                [$e->getMessage()],
            );
        } catch (Throwable) {
            return self::$result = new BootstrapResult(
                BootstrapState::FAILED,
                false,
                false,
                ['bootstrap_failure'],
            );
        }
    }

    public static function result(): ?BootstrapResult
    {
        return self::$result;
    }

    private static function registerFullMode(): BootstrapResult
    {
        // Product routes and concrete job handlers are composed by their owning packages.
        return new BootstrapResult(
            BootstrapState::READY,
            true,
            true,
            [],
        );
    }

    /**
     * @param list<string> $codes
     */
    private static function registerMinimalMode(BootstrapState $state, array $codes): BootstrapResult
    {
        // Domain mutation surfaces remain disabled until compatibility is restored.
        return new BootstrapResult(
            $state,
            true,
            false,
            $codes,
        );
    }

    private static function registerRoutes(Container $container, BootstrapResult $bootstrap): void
    {
        $checks = $container->database?->readinessChecks ?? [new BootstrapReadinessCheck($bootstrap)];
        $health = new SystemHealthService(
            $bootstrap,
            $checks,
            $container->services->clock,
            $container->config->pluginVersion,
        );
        $controller = new SystemStatusController(
            $health,
            new SystemStatusPresenter(),
            $container->services->requestIds,
            $container->services->apiErrors,
        );
        $wordpressRoutes = new WordPressRestRouteRegistry(
            new WordPressRestRequestMapper(),
            $container->services->requestIds,
            $container->services->apiErrors,
            $container->services->observability,
        );
        (new WordPressRestRouteHooks(new SystemRouteRegistrar($controller), $wordpressRoutes))->register();
        if ($bootstrap->ready && $container->database !== null) {
            $events = new EventController(
                $container->database->eventLifecycle,
                $container->database->eventAccess,
                $container->database->eventAccess,
                $container->delivery->authenticatedRequests,
                new EventRequestMapper(),
                new EventPresenter(),
            );
            (new WordPressRestRouteHooks(new EventRouteRegistrar($events), $wordpressRoutes))->register();
            $venues = new VenueController(
                $container->database->venues,
                $container->delivery->authenticatedRequests,
                new VenueRequestMapper(),
                new VenuePresenter(),
            );
            (new WordPressRestRouteHooks(new VenueRouteRegistrar($venues), $wordpressRoutes))->register();
            $eventConfigurations = new EventConfigurationController(
                $container->database->eventConfigurations,
                $container->delivery->authenticatedRequests,
                new EventConfigurationRequestMapper(),
                new EventConfigurationPresenter(),
            );
            (new WordPressRestRouteHooks(new EventConfigurationRouteRegistrar($eventConfigurations), $wordpressRoutes))->register();
            $memberships = new MembershipController(
                $container->database->memberships,
                $container->delivery->authenticatedRequests,
                new MembershipRequestMapper(),
                new MembershipPresenter(),
            );
            (new WordPressRestRouteHooks(new MembershipRouteRegistrar($memberships), $wordpressRoutes))->register();
            $membershipQueries = new MembershipQueryController(
                $container->database->membershipQueries,
                $container->delivery->authenticatedRequests,
                new MembershipQueryRequestMapper(),
                new MembershipPresenter(),
            );
            (new WordPressRestRouteHooks(new MembershipQueryRouteRegistrar($membershipQueries), $wordpressRoutes))->register();
            $invitations = new InvitationController(
                $container->database->invitations,
                $container->delivery->authenticatedRequests,
                new InvitationRequestMapper(),
                new InvitationPresenter(),
            );
            (new WordPressRestRouteHooks(new InvitationRouteRegistrar($invitations), $wordpressRoutes))->register();
            $invitationAccess = new InvitationAccessController(
                $container->database->invitationAccess,
                $container->delivery->authenticatedRequests,
                new InvitationAccessRequestMapper(),
                new InvitationPresenter(),
            );
            (new WordPressRestRouteHooks(new InvitationAccessRouteRegistrar($invitationAccess), $wordpressRoutes))->register();
            $guestBootstrap = new GuestBootstrapController(
                $container->database->guestAccess,
                new WordPressPublicBootstrapRateLimiter(),
                $container->services->requestIds,
                new GuestBootstrapRequestMapper(),
                new GuestSessionPresenter(),
            );
            (new WordPressRestRouteHooks(new GuestBootstrapRouteRegistrar($guestBootstrap), $wordpressRoutes))->register();
            $guestContexts = new GuestRequestContextFactory($container->database->guestAccess, $container->services->requestIds);
            $guestSession = new GuestSessionAccessController(
                $container->database->guestSessionAccess,
                $guestContexts,
                new GuestSessionAccessRequestMapper(),
                new GuestSessionAccessPresenter(),
            );
            (new WordPressRestRouteHooks(new GuestSessionAccessRouteRegistrar($guestSession), $wordpressRoutes))->register();
            $rsvp = new RsvpController(
                $container->database->attendees,
                $guestContexts,
                new RsvpRequestMapper(),
                new RsvpPresenter(),
            );
            (new WordPressRestRouteHooks(new RsvpRouteRegistrar($rsvp), $wordpressRoutes))->register();
            $attendees = new AttendeeController(
                $container->database->attendees,
                $container->delivery->authenticatedRequests,
                new AttendeeRequestMapper(),
                new AttendeePresenter(),
            );
            (new WordPressRestRouteHooks(new AttendeeRouteRegistrar($attendees), $wordpressRoutes))->register();
            $attendeeQueries = new AttendeeQueryController(
                $container->database->attendeeQueries,
                $container->delivery->authenticatedRequests,
                new AttendeeQueryRequestMapper(),
                new AttendeePresenter(),
            );
            (new WordPressRestRouteHooks(new AttendeeQueryRouteRegistrar($attendeeQueries), $wordpressRoutes))->register();
            $seatingPreparation = new SeatingPreparationController(
                $container->database->seating,
                $container->delivery->authenticatedRequests,
                new SeatingPreparationRequestMapper(),
                new SeatingPreparationPresenter(),
            );
            (new WordPressRestRouteHooks(new SeatingPreparationRouteRegistrar($seatingPreparation), $wordpressRoutes))->register();
            $seatingResources = new SeatingResourceController(
                $container->database->seatingResources,
                $container->delivery->authenticatedRequests,
                new SeatingResourceRequestMapper(),
                new SeatingResourcePresenter(),
            );
            (new WordPressRestRouteHooks(new SeatingResourceRouteRegistrar($seatingResources), $wordpressRoutes))->register();
            $seatingPlanning = new SeatingPlanningController(
                $container->database->seating,
                $container->delivery->authenticatedRequests,
                new SeatingPlanningRequestMapper(),
                new SeatingPlanningPresenter(),
            );
            (new WordPressRestRouteHooks(new SeatingPlanningRouteRegistrar($seatingPlanning), $wordpressRoutes))->register();
            $seatingRecommendations = new SeatingRecommendationController(
                $container->database->seatingRecommendations,
                $container->delivery->authenticatedRequests,
                new SeatingRecommendationRequestMapper(),
                new SeatingRecommendationPresenter(),
            );
            (new WordPressRestRouteHooks(new SeatingRecommendationRouteRegistrar($seatingRecommendations), $wordpressRoutes))->register();
            $seatingGroupMoves = new SeatingGroupMoveController(
                $container->database->seatingGroupMoves,
                $container->delivery->authenticatedRequests,
                new SeatingGroupMoveRequestMapper(),
                new SeatingGroupMovePresenter(),
            );
            (new WordPressRestRouteHooks(new SeatingGroupMoveRouteRegistrar($seatingGroupMoves), $wordpressRoutes))->register();
            $checkIn = new CheckInController(
                $container->database->checkIn,
                $container->database->checkIn,
                $container->delivery->authenticatedRequests,
                new CheckInRequestMapper(),
                new CheckInPresenter(),
            );
            (new WordPressRestRouteHooks(new CheckInRouteRegistrar($checkIn), $wordpressRoutes))->register();
            $templates = new TemplateController(
                $container->database->communications,
                $container->delivery->authenticatedRequests,
                new TemplateRequestMapper(),
                new TemplatePresenter(),
            );
            (new WordPressRestRouteHooks(new TemplateRouteRegistrar($templates), $wordpressRoutes))->register();
            $templateAccess = new TemplateAccessController(
                $container->database->templateAccess,
                $container->delivery->authenticatedRequests,
                new TemplateAccessRequestMapper(),
                new TemplateAccessPresenter(),
            );
            (new WordPressRestRouteHooks(new TemplateAccessRouteRegistrar($templateAccess), $wordpressRoutes))->register();
            $campaigns = new CampaignController(
                $container->database->communications,
                $container->delivery->authenticatedRequests,
                new CampaignRequestMapper(),
                new CampaignPresenter(),
            );
            (new WordPressRestRouteHooks(new CampaignRouteRegistrar($campaigns), $wordpressRoutes))->register();
            $campaignAccess = new CampaignAccessController(
                $container->database->campaignAccess,
                $container->delivery->authenticatedRequests,
                new CampaignAccessRequestMapper(),
                new CampaignAccessPresenter(),
            );
            (new WordPressRestRouteHooks(new CampaignAccessRouteRegistrar($campaignAccess), $wordpressRoutes))->register();
            $messageAccess = new MessageAccessController(
                $container->database->messageAccess,
                $container->delivery->authenticatedRequests,
                new MessageAccessRequestMapper(),
                new MessageAccessPresenter(),
            );
            (new WordPressRestRouteHooks(new MessageAccessRouteRegistrar($messageAccess), $wordpressRoutes))->register();
            $providerWebhooks = new ProviderWebhookController(
                $container->database->providers,
                $container->services->requestIds,
                new ProviderWebhookRequestMapper(),
                new ProviderWebhookPresenter(),
            );
            (new WordPressRestRouteHooks(new ProviderWebhookRouteRegistrar($providerWebhooks), $wordpressRoutes))->register();
            $imports = new ImportController(
                $container->database->imports,
                $container->delivery->authenticatedRequests,
                new ImportRequestMapper(),
                new ImportPresenter(),
            );
            (new WordPressRestRouteHooks(new ImportRouteRegistrar($imports), $wordpressRoutes))->register();
            $importPresenter = new ImportAdministrationPresenter();
            $importRequests = new ImportAdministrationRequestMapper();
            $importAdministration = new ImportAdministrationController(
                $container->database->importAdministration,
                $container->delivery->authenticatedRequests,
                $importRequests,
                $importPresenter,
            );
            $importUploads = new ImportUploadController(
                $container->database->imports,
                new HardenedImportUploadGuard(),
                $container->delivery->authenticatedRequests,
                $importRequests,
                $importPresenter,
            );
            (new WordPressRestRouteHooks(new ImportAdministrationRouteRegistrar($importAdministration, $importUploads), $wordpressRoutes))->register();
        }
    }

}
