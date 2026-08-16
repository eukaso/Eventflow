<?php

namespace EventFlow\Application\Import;

use InvalidArgumentException;

final readonly class ParsedImportSource
{
    /** @param list<string> $headers @param list<array<string, string|null>> $rows */
    public function __construct(public string $filename, public string $fileHash, public array $headers, public array $rows)
    {
        if ($filename === '' || !preg_match('/^[a-f0-9]{64}$/', $fileHash) || $headers === []) throw new InvalidArgumentException('invalid_parsed_import_source');
    }
}
