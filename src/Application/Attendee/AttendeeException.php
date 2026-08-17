<?php

namespace EventFlow\Application\Attendee;

use EventFlow\Application\Error\ControlledFailure;
use RuntimeException;

final class AttendeeException extends RuntimeException implements ControlledFailure
{
    public function __construct(public readonly string $safeCode) { parent::__construct($safeCode); }
    public function safeCode(): string { return $this->safeCode; }
}
