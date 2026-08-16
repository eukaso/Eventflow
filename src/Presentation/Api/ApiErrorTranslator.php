<?php

namespace EventFlow\Presentation\Api;

use EventFlow\Application\Error\ErrorCatalogue;
use EventFlow\Application\Error\ErrorCodeMapper;
use EventFlow\Application\Error\ErrorDetailKind;
use EventFlow\Application\Error\PublicErrorDetails;
use EventFlow\Application\Error\RequestId;
use EventFlow\Application\Error\RetryAfterDetails;
use EventFlow\Application\Error\Retryability;
use Throwable;

final readonly class ApiErrorTranslator
{
    public function __construct(
        private ErrorCatalogue $catalogue,
        private ErrorCodeMapper $mapper,
    ) {
    }

    public function translate(
        Throwable $failure,
        RequestId $requestId,
        ?PublicErrorDetails $details = null,
    ): ApiErrorResponse {
        $definition = $this->catalogue->require($this->mapper->publicCode($failure));
        $safeDetails = null;
        if (
            $details !== null
            && $definition->detailKind !== ErrorDetailKind::NONE
            && $details->kind() === $definition->detailKind
        ) {
            $safeDetails = $details->toArray();
        }

        $data = [
            'status' => $definition->httpStatus,
            'request_id' => $requestId->value,
            'retryability' => $definition->retryability->value,
        ];
        if ($safeDetails !== null) {
            $data['details'] = $safeDetails;
        }

        $headers = ['X-Request-ID' => $requestId->value];
        if ($details instanceof RetryAfterDetails && $safeDetails !== null) {
            $headers['Retry-After'] = (string) $details->retryAfterSeconds;
        }

        return new ApiErrorResponse(
            $definition->httpStatus,
            ['code' => $definition->code, 'message' => $definition->publicMessage, 'data' => $data],
            $headers,
        );
    }
}
