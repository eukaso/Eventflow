<?php

namespace EventFlow\Application\Import;

use InvalidArgumentException;

final readonly class ImportRowRecord
{
    /** @param array<string, mixed> $rawData @param array<string, mixed>|null $normalizedData @param list<string> $errors @param list<string> $warnings */
    public function __construct(
        public int $rowId,
        public int $jobId,
        public int $sourceRowNumber,
        public ImportRowStatus $status,
        public array $rawData,
        public ?array $normalizedData = null,
        public array $errors = [],
        public array $warnings = [],
    ) {
        if ($rowId < 1 || $jobId < 1 || $sourceRowNumber < 1) throw new InvalidArgumentException('invalid_import_row');
    }
}
