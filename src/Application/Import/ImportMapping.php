<?php

namespace EventFlow\Application\Import;

use InvalidArgumentException;

final readonly class ImportMapping
{
    /** @param array<string, string> $columns */
    public function __construct(public array $columns)
    {
        $allowed = ['primary_name', 'primary_email', 'primary_phone', 'capacity'];
        if (!isset($columns['primary_name']) || trim($columns['primary_name']) === '') throw new InvalidArgumentException('import_primary_name_mapping_required');
        foreach ($columns as $target => $source) if (!in_array($target, $allowed, true) || trim($source) === '') throw new InvalidArgumentException('invalid_import_mapping');
    }
}
