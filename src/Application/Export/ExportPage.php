<?php

namespace EventFlow\Application\Export;

final readonly class ExportPage
{
    /** @param list<ExportRecord> $exports */
    public function __construct(public array $exports, public ?int $nextAfterExportId) {}
}
