<?php

namespace EventFlow\Application\Migration;

use EventFlow\Application\Error\ControlledFailure;
use RuntimeException;
use Throwable;

final class MigrationException extends RuntimeException implements ControlledFailure
{
    public function __construct(
        public readonly string $safeCode,
        ?Throwable $previous = null,
    ) {
        parent::__construct($safeCode, 0, $previous);
    }

    public function safeCode(): string
    {
        return $this->safeCode;
    }
}
