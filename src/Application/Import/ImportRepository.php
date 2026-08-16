<?php

namespace EventFlow\Application\Import;

use DateTimeImmutable;
use EventFlow\Application\Persistence\EventScope;

interface ImportRepository
{
    /** @param list<array<string, string|null>> $rows */
    public function createStaged(EventScope $scope, ParsedImportSource $source, array $rows, ?int $actorUserId, DateTimeImmutable $now): ImportJobRecord;
    public function lockJob(EventScope $scope, int $jobId): ?ImportJobRecord;
    /** @return list<ImportRowRecord> */
    public function rowsForValidation(EventScope $scope, int $jobId): array;
    /** @param array<string, mixed>|null $normalized @param list<string> $errors @param list<string> $warnings */
    public function storeValidation(ImportRowRecord $row, ImportRowStatus $status, ?array $normalized, array $errors, array $warnings, DateTimeImmutable $now): void;
    public function finishValidation(ImportJobRecord $job, int $validRows, int $invalidRows, int $warningRows, array $mapping, DateTimeImmutable $now): ImportJobRecord;
    public function acquireLease(EventScope $scope, int $jobId, string $owner, string $token, DateTimeImmutable $now, DateTimeImmutable $expiresAt): ?ImportJobRecord;
    public function heartbeat(ImportJobRecord $job, string $token, DateTimeImmutable $now, DateTimeImmutable $expiresAt): void;
    /** @return list<ImportRowRecord> */
    public function readyBatch(ImportJobRecord $job, string $token, DateTimeImmutable $now, int $limit): array;
    public function markApplied(ImportRowRecord $row, int $invitationId, DateTimeImmutable $now): void;
    public function markFailed(ImportRowRecord $row, string $safeCode, DateTimeImmutable $now): void;
    public function reconcile(ImportJobRecord $job, string $token, DateTimeImmutable $now): ImportJobRecord;
}
