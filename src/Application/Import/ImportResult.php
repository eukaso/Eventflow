<?php
namespace EventFlow\Application\Import;
final readonly class ImportResult{public function __construct(public int $jobId,public ImportStatus $status,public int $totalRows,public int $readyRows,public int $warningRows,public int $invalidRows,public int $appliedRows,public int $skippedRows,public int $failedRows){if($jobId<1)throw new ImportException('import_result_invalid');}}
