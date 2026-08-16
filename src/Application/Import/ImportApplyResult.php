<?php

namespace EventFlow\Application\Import;

final readonly class ImportApplyResult
{
    public function __construct(public ImportJobRecord $job, public int $processedRows, public int $appliedRows, public int $failedRows) {}
}
