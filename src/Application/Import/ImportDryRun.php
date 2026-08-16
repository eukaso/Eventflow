<?php

namespace EventFlow\Application\Import;

final readonly class ImportDryRun
{
    public function __construct(public int $totalRows, public int $readyRows, public int $invalidRows, public int $warningRows) {}
}
