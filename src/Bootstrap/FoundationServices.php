<?php

namespace EventFlow\Bootstrap;

use EventFlow\Application\Clock\Clock;
use EventFlow\Application\Error\ErrorCatalogue;
use EventFlow\Application\Error\ErrorCodeMapper;
use EventFlow\Application\Error\RequestIdFactory;
use EventFlow\Application\Security\SecureRandom;
use EventFlow\Application\Observability\{Metrics, ObservabilityRedactor, ObservabilityService, StructuredLogger};
use EventFlow\Presentation\Api\ApiErrorTranslator;

final readonly class FoundationServices
{
    public function __construct(
        public Clock $clock,
        public SecureRandom $random,
        public SchemaCompatibilityChecker $schemaCompatibility,
        public ErrorCatalogue $errors,
        public ErrorCodeMapper $errorCodeMapper,
        public RequestIdFactory $requestIds,
        public ApiErrorTranslator $apiErrors,
        public ObservabilityRedactor $observabilityRedactor,
        public StructuredLogger $logger,
        public Metrics $metrics,
        public ObservabilityService $observability,
    ) {
    }
}
