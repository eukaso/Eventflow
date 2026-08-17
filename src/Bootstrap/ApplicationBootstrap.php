<?php

namespace EventFlow\Bootstrap;

use EventFlow\Application\Health\SystemHealthService;
use EventFlow\Infrastructure\Config\ConfigException;
use EventFlow\Infrastructure\Config\ConfigLoader;
use EventFlow\Infrastructure\Health\BootstrapReadinessCheck;
use EventFlow\Presentation\Api\{EventController, EventPresenter, EventRequestMapper, EventRouteRegistrar, SystemRouteRegistrar, SystemStatusController, SystemStatusPresenter};
use EventFlow\Presentation\WordPress\{WordPressRestRequestMapper, WordPressRestRouteHooks, WordPressRestRouteRegistry};
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
        }
    }

}
