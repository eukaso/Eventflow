<?php

namespace EventFlow\Application\Event;

use EventFlow\Application\Error\ControlledFailure;
use RuntimeException;
use Throwable;

final class EventLifecycleException extends RuntimeException implements ControlledFailure
{
    public function __construct(public readonly string $safeCode, ?Throwable $previous = null)
    {
        parent::__construct($safeCode, 0, $previous);
    }

    public function safeCode(): string
    {
        return $this->safeCode;
    }
}
