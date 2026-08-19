<?php

namespace EventFlow\Application\EventConfiguration;

use EventFlow\Application\Error\ControlledFailure;
use RuntimeException;

final class EventConfigurationException extends RuntimeException implements ControlledFailure
{
    public function __construct(public readonly string $safeCode) { parent::__construct($safeCode); }
    public function safeCode(): string { return $this->safeCode; }
}
