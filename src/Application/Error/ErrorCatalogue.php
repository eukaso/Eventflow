<?php

namespace EventFlow\Application\Error;

use InvalidArgumentException;

final class ErrorCatalogue
{
    /** @var array<string, ErrorDefinition> */
    private array $definitions = [];

    /** @param iterable<ErrorDefinition> $definitions */
    public function __construct(iterable $definitions)
    {
        foreach ($definitions as $definition) {
            if (!$definition instanceof ErrorDefinition) {
                throw new InvalidArgumentException('invalid_error_catalogue_entry');
            }
            if (isset($this->definitions[$definition->code])) {
                throw new InvalidArgumentException('duplicate_error_catalogue_code');
            }
            $this->definitions[$definition->code] = $definition;
        }
        if (!isset($this->definitions['internal_error'])) {
            throw new InvalidArgumentException('internal_error_definition_required');
        }
    }

    public function require(string $code): ErrorDefinition
    {
        return $this->definitions[$code] ?? $this->definitions['internal_error'];
    }

    public function has(string $code): bool
    {
        return isset($this->definitions[$code]);
    }

    /** @return list<ErrorDefinition> */
    public function all(): array
    {
        return array_values($this->definitions);
    }
}
