<?php

namespace EventFlow\Infrastructure\Deployment;

use DateTimeImmutable;
use DateTimeZone;
use EventFlow\Application\Deployment\BackupEvidenceVerifier;
use EventFlow\Application\Deployment\VerifiedBackupEvidence;
use JsonException;
use RuntimeException;

final readonly class LocalBackupEvidenceVerifier implements BackupEvidenceVerifier
{
    private const MAXIMUM_EVIDENCE_BYTES = 65536;
    private const MAXIMUM_AGE_SECONDS = 86400;

    public function verify(string $evidencePath, string $artifactSha256, int $nowEpoch): VerifiedBackupEvidence
    {
        if (!$this->sha($artifactSha256) || $nowEpoch < 1 || is_link($evidencePath) || !is_file($evidencePath)) {
            throw new RuntimeException('backup_evidence_input_invalid');
        }
        $size = filesize($evidencePath);
        if ($size === false || $size < 2 || $size > self::MAXIMUM_EVIDENCE_BYTES) {
            throw new RuntimeException('backup_evidence_input_invalid');
        }
        try {
            $evidence = json_decode((string) file_get_contents($evidencePath), true, 32, JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            throw new RuntimeException('backup_evidence_json_invalid', 0, $exception);
        }
        if (!is_array($evidence)
            || ($evidence['format_version'] ?? null) !== 1
            || !is_string($evidence['evidence_id'] ?? null)
            || preg_match('/^[a-z0-9][a-z0-9._-]{7,99}$/', $evidence['evidence_id']) !== 1
            || !in_array($evidence['target_environment'] ?? null, ['staging', 'production'], true)
            || !is_string($evidence['artifact_sha256'] ?? null)
            || !hash_equals($artifactSha256, $evidence['artifact_sha256'])
            || !is_string($evidence['created_at'] ?? null)
            || !is_string($evidence['restore_procedure_id'] ?? null)
            || preg_match('/^[a-z0-9][a-z0-9._-]{7,99}$/', $evidence['restore_procedure_id']) !== 1
            || !is_array($evidence['restore_rehearsal'] ?? null)
            || ($evidence['restore_rehearsal']['status'] ?? null) !== 'passed'
        ) {
            throw new RuntimeException('backup_evidence_contract_invalid');
        }
        $createdAt = $this->utc($evidence['created_at']);
        $restoredAt = $this->utc($evidence['restore_rehearsal']['completed_at'] ?? null);
        if ($createdAt === null || $restoredAt === null
            || $createdAt->getTimestamp() > $nowEpoch + 300
            || $createdAt->getTimestamp() < $nowEpoch - self::MAXIMUM_AGE_SECONDS
            || $restoredAt < $createdAt
            || $restoredAt->getTimestamp() > $nowEpoch + 300
        ) {
            throw new RuntimeException('backup_evidence_stale_or_invalid');
        }
        [$databaseSha, $databaseBytes] = $this->archive($evidence['database_backup'] ?? null);
        [$filesSha, $filesBytes] = $this->archive($evidence['files_backup'] ?? null);
        if (($evidence['restore_rehearsal']['database_sha256'] ?? null) !== $databaseSha
            || ($evidence['restore_rehearsal']['files_sha256'] ?? null) !== $filesSha
            || ($evidence['restore_rehearsal']['database_bytes'] ?? null) !== $databaseBytes
            || ($evidence['restore_rehearsal']['files_bytes'] ?? null) !== $filesBytes
        ) {
            throw new RuntimeException('backup_restore_rehearsal_mismatch');
        }
        $evidenceSha = hash_file('sha256', $evidencePath);
        if (!is_string($evidenceSha)) {
            throw new RuntimeException('backup_evidence_hash_unavailable');
        }
        return new VerifiedBackupEvidence(
            $evidence['evidence_id'],
            $evidenceSha,
            $evidence['target_environment'],
            $createdAt->format('Y-m-d\TH:i:s\Z'),
            $databaseSha,
            $filesSha,
            $evidence['restore_procedure_id'],
        );
    }

    /** @return array{string,int} */
    private function archive(mixed $record): array
    {
        if (!is_array($record)
            || !is_string($record['path'] ?? null)
            || !is_string($record['sha256'] ?? null)
            || !$this->sha($record['sha256'])
            || !is_int($record['bytes'] ?? null)
            || $record['bytes'] < 1
            || !$this->absolute($record['path'])
            || is_link($record['path'])
            || !is_file($record['path'])
            || !is_readable($record['path'])
            || filesize($record['path']) !== $record['bytes']
        ) {
            throw new RuntimeException('backup_archive_invalid');
        }
        $actual = hash_file('sha256', $record['path']);
        if (!is_string($actual) || !hash_equals($record['sha256'], $actual)) {
            throw new RuntimeException('backup_archive_hash_mismatch');
        }
        return [$actual, $record['bytes']];
    }

    private function utc(mixed $value): ?DateTimeImmutable
    {
        if (!is_string($value) || preg_match('/^[0-9]{4}-[0-9]{2}-[0-9]{2}T[0-9]{2}:[0-9]{2}:[0-9]{2}Z$/', $value) !== 1) {
            return null;
        }
        $date = DateTimeImmutable::createFromFormat('!Y-m-d\TH:i:s\Z', $value, new DateTimeZone('UTC'));
        return $date instanceof DateTimeImmutable && $date->format('Y-m-d\TH:i:s\Z') === $value ? $date : null;
    }

    private function sha(string $value): bool
    {
        return preg_match('/^[a-f0-9]{64}$/', $value) === 1;
    }

    private function absolute(string $path): bool
    {
        return str_starts_with($path, '/') || preg_match('/^[A-Za-z]:[\\\\\/]/', $path) === 1;
    }
}
