<?php

namespace EventFlow\Bootstrap;

use EventFlow\Application\Error\CoreErrorCatalogue;
use EventFlow\Application\Error\ErrorCodeMapper;
use EventFlow\Application\Error\RequestIdFactory;
use EventFlow\Infrastructure\Clock\SystemClock;
use EventFlow\Infrastructure\Config\Config;
use EventFlow\Infrastructure\Security\PhpSecureRandom;
use EventFlow\Presentation\Api\ApiErrorTranslator;

final readonly class Container
{
    private function __construct(
        public Config $config,
        public FoundationServices $services,
        public ?DatabaseFoundation $database,
    ) {
    }

    public static function createFoundation(Config $config, ?object $wpdb = null): self
    {
        $clock = new SystemClock();
        $random = new PhpSecureRandom();
        $schemaCompatibility = new SchemaCompatibilityChecker();
        $errors = CoreErrorCatalogue::create();
        $errorCodeMapper = new ErrorCodeMapper($errors);
        $services = new FoundationServices(
            clock: $clock,
            random: $random,
            schemaCompatibility: $schemaCompatibility,
            errors: $errors,
            errorCodeMapper: $errorCodeMapper,
            requestIds: new RequestIdFactory($random),
            apiErrors: new ApiErrorTranslator($errors, $errorCodeMapper),
        );

        return new self(
            $config,
            $services,
            $wpdb === null ? null : DatabaseFoundation::create($wpdb, $config, $services),
        );
    }
}
