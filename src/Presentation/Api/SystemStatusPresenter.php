<?php

namespace EventFlow\Presentation\Api;

use EventFlow\Application\Error\RequestId;
use EventFlow\Application\Health\HealthReport;
use EventFlow\Application\Health\ReadinessCheckResult;
use EventFlow\Application\Health\ReadinessReport;

final readonly class SystemStatusPresenter
{
    public function health(HealthReport $report, RequestId $requestId): SystemStatusResponse
    {
        return new SystemStatusResponse(
            $report->healthy ? 200 : 503,
            [
                'status' => $report->status->value,
                'healthy' => $report->healthy,
                'code' => $report->code->value,
                'version' => $report->applicationVersion,
                'generated_at' => $report->generatedAt->format('Y-m-d\TH:i:s\Z'),
                'request_id' => $requestId->value,
            ],
            $this->headers($requestId),
        );
    }

    public function readiness(ReadinessReport $report, RequestId $requestId): SystemStatusResponse
    {
        return new SystemStatusResponse(
            $report->ready ? 200 : 503,
            [
                'status' => $report->status->value,
                'healthy' => $report->healthy,
                'ready' => $report->ready,
                'version' => $report->applicationVersion,
                'generated_at' => $report->generatedAt->format('Y-m-d\TH:i:s\Z'),
                'request_id' => $requestId->value,
                'checks' => array_map(
                    static fn (ReadinessCheckResult $check): array => [
                        'id' => $check->identifier,
                        'impact' => $check->impact->value,
                        'status' => $check->status->value,
                        'code' => $check->code->value,
                    ],
                    $report->checks,
                ),
            ],
            $this->headers($requestId),
        );
    }

    /** @return array<string, string> */
    private function headers(RequestId $requestId): array
    {
        return [
            'X-Request-ID' => $requestId->value,
            'Cache-Control' => 'no-store, max-age=0',
        ];
    }
}
