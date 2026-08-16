<?php

namespace EventFlow\Application\Seating;

final readonly class SeatingReadiness
{
    /** @param list<string> $errors @param list<string> $warnings */
    public function __construct(public bool $ready, public array $errors, public array $warnings, public string $fingerprint) {}
}
