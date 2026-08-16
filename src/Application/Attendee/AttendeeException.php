<?php

namespace EventFlow\Application\Attendee;

use RuntimeException;

final class AttendeeException extends RuntimeException
{
    public function __construct(public readonly string $safeCode) { parent::__construct($safeCode); }
}
