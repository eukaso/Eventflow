<?php

namespace EventFlow\Application\Import;

use RuntimeException;

final class ImportException extends RuntimeException
{
    public function __construct(public readonly string $safeCode) { parent::__construct($safeCode); }
}
