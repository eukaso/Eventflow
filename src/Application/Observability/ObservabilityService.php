<?php

namespace EventFlow\Application\Observability;

use Throwable;
use EventFlow\Application\Error\{ErrorCatalogue, ErrorCodeMapper, RequestId};

final readonly class ObservabilityService
{
    public function __construct(
        private StructuredLogger $logger,
        private Metrics $metrics,
        private ErrorCodeMapper $codes,
        private ErrorCatalogue $catalogue,
    ) {
    }

    /** @param array<string, mixed> $context */
    public function failure(Throwable $failure, RequestId $requestId, array $context = []): string
    {
        $code = $this->codes->publicCode($failure);
        $definition = $this->catalogue->require($code);
        try {
            $this->logger->log($definition->httpStatus >= 500 ? 'error' : 'warning', 'application.failure', $requestId, [
                ...$context,
                'public_code' => $code,
                'status_family' => intdiv($definition->httpStatus, 100) . 'xx',
                'failure_class' => $failure::class,
            ]);
        } catch (Throwable) {
            // Observability transport failure must not replace the application failure.
        }
        try {
            $this->metrics->increment('eventflow_failures_total', ['public_code' => $code]);
        } catch (Throwable) {
            // Metric transport failure must not alter request semantics.
        }
        return $code;
    }

    public function requestCompleted(string $transport, bool $success): void
    {
        try {
            $this->metrics->increment('eventflow_requests_total', ['transport' => $transport, 'outcome' => $success ? 'success' : 'failure']);
        } catch (Throwable) {
            // Metric transport failure must not alter request semantics.
        }
    }
}
