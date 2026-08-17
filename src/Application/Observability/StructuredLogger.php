<?php

namespace EventFlow\Application\Observability;

use EventFlow\Application\Clock\Clock;
use EventFlow\Application\Error\RequestId;

final readonly class StructuredLogger
{
    private const LEVELS = ['debug' => 10, 'info' => 20, 'warning' => 30, 'error' => 40, 'critical' => 50];

    public function __construct(
        private LogSink $sink,
        private ObservabilityRedactor $redactor,
        private Clock $clock,
        private string $minimumLevel,
    ) {
        if (!isset(self::LEVELS[$minimumLevel])) {
            throw new ObservabilityException('observability_log_level_invalid');
        }
    }

    /** @param array<string, mixed> $context */
    public function log(string $level, string $event, RequestId $requestId, array $context = []): void
    {
        if (!isset(self::LEVELS[$level]) || !preg_match('/^[a-z][a-z0-9_.]{2,99}$/', $event)) {
            throw new ObservabilityException('observability_log_record_invalid');
        }
        if (self::LEVELS[$level] < self::LEVELS[$this->minimumLevel]) {
            return;
        }
        $this->sink->write([
            'timestamp' => $this->clock->now()->format(DATE_ATOM),
            'level' => $level,
            'event' => $event,
            'request_id' => $requestId->value,
            'context' => $this->redactor->redact($context),
        ]);
    }
}
