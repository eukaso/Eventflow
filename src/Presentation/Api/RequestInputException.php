<?php

namespace EventFlow\Presentation\Api;

use EventFlow\Application\Error\{ControlledFailure, PublicErrorDetails};
use RuntimeException;

final class RequestInputException extends RuntimeException implements ControlledFailure
{
    public function __construct(
        public readonly string $safeCode,
        public readonly ?PublicErrorDetails $details = null,
    ) {
        parent::__construct($safeCode);
    }

    public function safeCode(): string { return $this->safeCode; }
}
