<?php

namespace EventFlow\Application\Observability;

use Throwable;
use EventFlow\Application\Authorization\{AuthorizationService, Capability, PrincipalContext};
use EventFlow\Application\Clock\Clock;
use EventFlow\Application\Error\RequestId;
use EventFlow\Application\Persistence\EventScope;

final class DiagnosticService
{
    /** @var list<DiagnosticSource> */
    private array $sources;

    /** @param iterable<DiagnosticSource> $sources */
    public function __construct(
        private readonly AuthorizationService $authorization,
        private readonly ObservabilityRedactor $redactor,
        private readonly Clock $clock,
        iterable $sources,
    ) {
        $this->sources = [];
        $seen = [];
        foreach ($sources as $source) {
            if (!$source instanceof DiagnosticSource || !preg_match('/^[a-z][a-z0-9_]{2,63}$/', $source->identifier()) || isset($seen[$source->identifier()])) {
                throw new ObservabilityException('diagnostic_source_invalid');
            }
            $seen[$source->identifier()] = true;
            $this->sources[] = $source;
        }
        if ($this->sources === []) {
            throw new ObservabilityException('diagnostic_source_required');
        }
    }

    public function export(PrincipalContext $principal, EventScope $scope, RequestId $requestId): DiagnosticBundle
    {
        $this->authorization->requireEventCapability($principal, $scope, Capability::VIEW_AUDIT);
        $sections = [];
        foreach ($this->sources as $source) {
            try {
                $sections[$source->identifier()] = $this->redactor->redact($source->snapshot());
            } catch (Throwable) {
                $sections[$source->identifier()] = ['status' => 'unavailable', 'code' => 'diagnostic_source_failed'];
            }
        }
        return new DiagnosticBundle($requestId->value, $scope->eventId, $this->clock->now(), $sections);
    }
}
