<?php

namespace EventFlow\Application\Import;

use DateTimeImmutable;
use EventFlow\Application\Persistence\EventScope;
use InvalidArgumentException;

final readonly class ImportJobRecord
{
    public function __construct(
        public int $jobId,
        public EventScope $eventScope,
        public ImportStatus $status,
        public string $sourceFilename,
        public string $sourceFileHash,
        public int $totalRows,
        public int $validRows,
        public int $invalidRows,
        public int $appliedRows,
        public int $failedRows,
        public ?string $leaseToken = null,
        public ?DateTimeImmutable $leaseExpiresAt = null,
        public int $revision = 1,
        public int $warningRows = 0,
        public int $skippedRows = 0,
        /** @var array<string,string> */ public array $mapping = [],
        public ?DateTimeImmutable $uploadedAt = null,
        public ?DateTimeImmutable $validatedAt = null,
        public ?DateTimeImmutable $completedAt = null,
        public ?DateTimeImmutable $cancelledAt = null,
    ) {
        if ($jobId < 1 || $revision < 1 || $sourceFilename === '' || !preg_match('/^[a-f0-9]{64}$/', $sourceFileHash)) throw new InvalidArgumentException('invalid_import_job');
    }
}
