<?php

namespace EventFlow\Bootstrap;

use EventFlow\Application\Health\SystemHealthService;
use EventFlow\Infrastructure\Config\ConfigException;
use EventFlow\Infrastructure\Config\ConfigLoader;
use EventFlow\Infrastructure\Health\BootstrapReadinessCheck;
use EventFlow\Presentation\Api\{AttendeeController, AttendeePresenter, AttendeeRequestMapper, AttendeeRouteRegistrar, CheckInController, CheckInPresenter, CheckInRequestMapper, CheckInRouteRegistrar, EventController, EventPresenter, EventRequestMapper, EventRouteRegistrar, GuestBootstrapController, GuestBootstrapRequestMapper, GuestBootstrapRouteRegistrar, GuestRequestContextFactory, GuestSessionPresenter, InvitationController, InvitationPresenter, InvitationRequestMapper, InvitationRouteRegistrar, MembershipController, MembershipPresenter, MembershipRequestMapper, MembershipRouteRegistrar, RsvpController, RsvpPresenter, RsvpRequestMapper, RsvpRouteRegistrar, SeatingPlanningController, SeatingPlanningPresenter, SeatingPlanningRequestMapper, SeatingPlanningRouteRegistrar, SeatingPreparationController, SeatingPreparationPresenter, SeatingPreparationRequestMapper, SeatingPreparationRouteRegistrar, SystemRouteRegistrar, SystemStatusController, SystemStatusPresenter, TemplateController, TemplatePresenter, TemplateRequestMapper, TemplateRouteRegistrar};
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
                $container->delivery->authenticatedRequests,
                new EventRequestMapper(),
                new EventPresenter(),
            );
            (new WordPressRestRouteHooks(new EventRouteRegistrar($events), $wordpressRoutes))->register();
            $memberships = new MembershipController(
                $container->database->memberships,
                $container->delivery->authenticatedRequests,
                new MembershipRequestMapper(),
                new MembershipPresenter(),
            );
            (new WordPressRestRouteHooks(new MembershipRouteRegistrar($memberships), $wordpressRoutes))->register();
            $invitations = new InvitationController(
                $container->database->invitations,
                $container->delivery->authenticatedRequests,
                new InvitationRequestMapper(),
                new InvitationPresenter(),
            );
            (new WordPressRestRouteHooks(new InvitationRouteRegistrar($invitations), $wordpressRoutes))->register();
            $guestBootstrap = new GuestBootstrapController(
                $container->database->guestAccess,
                new WordPressPublicBootstrapRateLimiter(),
                $container->services->requestIds,
                new GuestBootstrapRequestMapper(),
                new GuestSessionPresenter(),
            );
            (new WordPressRestRouteHooks(new GuestBootstrapRouteRegistrar($guestBootstrap), $wordpressRoutes))->register();
            $rsvp = new RsvpController(
                $container->database->attendees,
                new GuestRequestContextFactory($container->database->guestAccess, $container->services->requestIds),
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
            $seatingPreparation = new SeatingPreparationController(
                $container->database->seating,
                $container->delivery->authenticatedRequests,
                new SeatingPreparationRequestMapper(),
                new SeatingPreparationPresenter(),
            );
            (new WordPressRestRouteHooks(new SeatingPreparationRouteRegistrar($seatingPreparation), $wordpressRoutes))->register();
            $seatingPlanning = new SeatingPlanningController(
                $container->database->seating,
                $container->delivery->authenticatedRequests,
                new SeatingPlanningRequestMapper(),
                new SeatingPlanningPresenter(),
            );
            (new WordPressRestRouteHooks(new SeatingPlanningRouteRegistrar($seatingPlanning), $wordpressRoutes))->register();
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
        }
    }

}
