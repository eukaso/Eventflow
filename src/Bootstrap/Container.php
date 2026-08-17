<?php

namespace EventFlow\Bootstrap;

use EventFlow\Application\Error\CoreErrorCatalogue;
use EventFlow\Application\Error\ErrorCodeMapper;
use EventFlow\Application\Error\RequestIdFactory;
use EventFlow\Application\Observability\{MetricDefinition, Metrics, ObservabilityRedactor, ObservabilityService, StructuredLogger};
use EventFlow\Infrastructure\Clock\SystemClock;
use EventFlow\Infrastructure\Config\Config;
use EventFlow\Infrastructure\Security\PhpSecureRandom;
use EventFlow\Infrastructure\Observability\{WordPressMetricSink, WordPressStructuredLogSink};
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
        $observabilityRedactor = new ObservabilityRedactor();
        $logger = new StructuredLogger(new WordPressStructuredLogSink(), $observabilityRedactor, $clock, $config->logLevel);
        $metrics = new Metrics(new WordPressMetricSink(), [
            new MetricDefinition('eventflow_failures_total', [
                'public_code' => array_map(static fn ($definition): string => $definition->code, $errors->all()),
            ]),
            new MetricDefinition('eventflow_requests_total', [
                'transport' => ['api', 'worker', 'cli', 'scheduler'],
                'outcome' => ['success', 'failure'],
            ]),
        ]);
        $observability = new ObservabilityService($logger, $metrics, $errorCodeMapper, $errors);
        $services = new FoundationServices(
            clock: $clock,
            random: $random,
            schemaCompatibility: $schemaCompatibility,
            errors: $errors,
            errorCodeMapper: $errorCodeMapper,
            requestIds: new RequestIdFactory($random),
            apiErrors: new ApiErrorTranslator($errors, $errorCodeMapper, $observability),
            observabilityRedactor: $observabilityRedactor,
            logger: $logger,
            metrics: $metrics,
            observability: $observability,
        );

        return new self(
            $config,
            $services,
            $wpdb === null ? null : DatabaseFoundation::create($wpdb, $config, $services),
        );
    }
}
