<?php

namespace EventFlow\Bootstrap;

use EventFlow\Infrastructure\Config\ConfigException;
use EventFlow\Infrastructure\Config\ConfigLoader;
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
            $container = Container::createFoundation($config);

            // IMP-003 will replace this placeholder with a schema metadata repository.
            $installedSchemaVersion = self::readInstalledSchemaVersionPlaceholder();

            $compatibility = $container
                ->schemaCompatibilityChecker()
                ->check($config->expectedSchemaVersion, $installedSchemaVersion);

            return self::$result = match ($compatibility) {
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
        // REST, workers, admin and CLI registration will be added in later IMP packages.
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
        // Minimal health/readiness/admin migration surfaces are added later.
        return new BootstrapResult(
            $state,
            true,
            false,
            $codes,
        );
    }

    private static function readInstalledSchemaVersionPlaceholder(): ?int
    {
        // Intentionally no migration or write on normal bootstrap.
        // IMP-003 will replace this with read-only schema metadata lookup.
        return defined('EVENTFLOW_INSTALLED_SCHEMA_VERSION')
            ? (int) EVENTFLOW_INSTALLED_SCHEMA_VERSION
            : null;
    }
}
