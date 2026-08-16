<?php

namespace EventFlow\Application\Error;

use InvalidArgumentException;

final readonly class ErrorDefinition
{
    public function __construct(
        public string $code,
        public int $httpStatus,
        public Retryability $retryability,
        public string $publicMessage,
        public ErrorDetailKind $detailKind = ErrorDetailKind::NONE,
    ) {
        if (!preg_match('/^[a-z][a-z0-9_]{2,99}$/', $code)) {
            throw new InvalidArgumentException('invalid_error_catalogue_code');
        }
        if ($httpStatus < 400 || $httpStatus > 599 || $publicMessage === '' || strlen($publicMessage) > 300) {
            throw new InvalidArgumentException('invalid_error_catalogue_definition');
        }
    }
}
