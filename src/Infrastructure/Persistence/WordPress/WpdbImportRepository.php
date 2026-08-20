<?php

namespace EventFlow\Infrastructure\Persistence\WordPress;

use DateTimeImmutable;
use DateTimeZone;
use EventFlow\Application\Import\ImportJobRecord;
use EventFlow\Application\Import\ImportJobPage;
use EventFlow\Application\Import\ImportRowPage;
use EventFlow\Application\Import\ImportAdministrationRepository;
use EventFlow\Application\Import\ImportRepository;
use EventFlow\Application\Import\ImportRowRecord;
use EventFlow\Application\Import\ImportRowStatus;
use EventFlow\Application\Import\ImportStatus;
use EventFlow\Application\Import\ParsedImportSource;
use EventFlow\Application\Persistence\EventScope;
use EventFlow\Infrastructure\Persistence\PersistenceException;
use EventFlow\Infrastructure\Persistence\TableName;

final class WpdbImportRepository extends AbstractWpdbRepository implements ImportRepository, ImportAdministrationRepository
{
    public function createStaged(EventScope $scope, ParsedImportSource $source, array $rows, ?int $actorUserId, DateTimeImmutable $now): ImportJobRecord
    {
        if ($actorUserId !== null && $actorUserId < 1) throw new PersistenceException('import_actor_invalid');
        $jobs = $this->table(TableName::IMPORT_JOBS); $actorSql = $actorUserId === null ? 'NULL' : '%d';
        $parameters = [$scope->eventId, 'invitations', ImportStatus::STAGED->value, $source->filename, $source->fileHash, count($rows), $this->time($now)]; if ($actorUserId !== null) $parameters[] = $actorUserId; $parameters[] = $this->time($now); $parameters[] = $this->time($now);
        if ($this->database->execute("INSERT INTO {$jobs} (event_id, import_type, import_status, source_filename, source_file_hash, total_rows, uploaded_at, created_by_user_id, created_at, updated_at) VALUES (%d, %s, %s, %s, %s, %d, %s, {$actorSql}, %s, %s)", $parameters) !== 1) throw new PersistenceException('import_job_create_failed');
        $jobId = $this->database->lastInsertId(); $table = $this->table(TableName::IMPORT_ROWS); $number = 1;
        foreach ($rows as $row) { if (!is_array($row)) throw new PersistenceException('import_row_invalid'); $json = $this->json($row); if ($this->database->execute("INSERT INTO {$table} (import_job_id, event_id, source_row_number, raw_data, row_status, created_at, updated_at) VALUES (%d, %d, %d, %s, %s, %s, %s)", [$jobId, $scope->eventId, $number++, $json, ImportRowStatus::PENDING->value, $this->time($now), $this->time($now)]) !== 1) throw new PersistenceException('import_row_stage_failed'); }
        return new ImportJobRecord($jobId, $scope, ImportStatus::STAGED, $source->filename, $source->fileHash, count($rows), 0, 0, 0, 0);
    }

    public function lockJob(EventScope $scope, int $jobId): ?ImportJobRecord
    {
        $table = $this->table(TableName::IMPORT_JOBS); $row = $this->database->fetchRow('SELECT '.$this->jobColumns()." FROM {$table} WHERE event_id = %d AND import_job_id = %d LIMIT 1 FOR UPDATE", [$scope->eventId, $jobId]);
        return $row === null ? null : $this->job($row, $scope);
    }

    public function listJobs(EventScope$scope,int$limit,?int$after,?ImportStatus$status):ImportJobPage{$table=$this->table(TableName::IMPORT_JOBS);$where='';$parameters=[$scope->eventId];if($after!==null){$where.=' AND import_job_id>%d';$parameters[]=$after;}if($status!==null){$where.=' AND import_status=%s';$parameters[]=$status->value;}$parameters[]=$limit+1;$rows=$this->database->fetchAll('SELECT '.$this->jobColumns()." FROM {$table} WHERE event_id=%d{$where} ORDER BY import_job_id ASC LIMIT %d",$parameters);$more=count($rows)>$limit;if($more)array_pop($rows);$jobs=array_map(fn(array$row):ImportJobRecord=>$this->job($row,$scope),$rows);return new ImportJobPage($jobs,$more&&$jobs!==[]?$jobs[array_key_last($jobs)]->jobId:null);}
    public function findJob(EventScope$scope,int$jobId):?ImportJobRecord{$table=$this->table(TableName::IMPORT_JOBS);$row=$this->database->fetchRow('SELECT '.$this->jobColumns()." FROM {$table} WHERE event_id=%d AND import_job_id=%d LIMIT 1",[$scope->eventId,$jobId]);return$row===null?null:$this->job($row,$scope);}
    public function listRows(EventScope$scope,int$jobId,int$limit,?int$after,?ImportRowStatus$status):ImportRowPage{$table=$this->table(TableName::IMPORT_ROWS);$where='';$parameters=[$scope->eventId,$jobId];if($after!==null){$where.=' AND import_row_id>%d';$parameters[]=$after;}if($status!==null){$where.=' AND row_status=%s';$parameters[]=$status->value;}$parameters[]=$limit+1;$rows=$this->database->fetchAll("SELECT import_row_id,import_job_id,source_row_number,row_status,raw_data,normalized_data,validation_errors,validation_warnings FROM {$table} WHERE event_id=%d AND import_job_id=%d{$where} ORDER BY import_row_id ASC LIMIT %d",$parameters);$more=count($rows)>$limit;if($more)array_pop($rows);$records=array_map(fn(array$row):ImportRowRecord=>$this->row($row),$rows);return new ImportRowPage($records,$more&&$records!==[]?$records[array_key_last($records)]->rowId:null);}
    public function markApplyQueued(EventScope$scope,ImportJobRecord$current,DateTimeImmutable$now):ImportJobRecord{$table=$this->table(TableName::IMPORT_JOBS);$affected=$this->database->execute("UPDATE {$table} SET import_status='applying',import_revision=import_revision+1,updated_at=%s WHERE event_id=%d AND import_job_id=%d AND import_revision=%d AND import_status='validated'",[$this->time($now),$scope->eventId,$current->jobId,$current->revision]);if($affected!==1)throw new PersistenceException('resource_modified');return$this->copy($current,ImportStatus::APPLYING,$current->revision+1);}
    public function cancelJob(EventScope$scope,ImportJobRecord$current,DateTimeImmutable$now):ImportJobRecord{$table=$this->table(TableName::IMPORT_JOBS);$affected=$this->database->execute("UPDATE {$table} SET import_status='cancelled',cancelled_at=%s,import_revision=import_revision+1,updated_at=%s WHERE event_id=%d AND import_job_id=%d AND import_revision=%d AND import_status IN ('uploaded','staged','validated')",[$this->time($now),$this->time($now),$scope->eventId,$current->jobId,$current->revision]);if($affected!==1)throw new PersistenceException('resource_modified');return$this->copy($current,ImportStatus::CANCELLED,$current->revision+1,$now);}

    public function rowsForValidation(EventScope $scope, int $jobId): array
    {
        $table = $this->table(TableName::IMPORT_ROWS); $rows = $this->database->fetchAll("SELECT import_row_id, import_job_id, source_row_number, row_status, raw_data, normalized_data, validation_errors, validation_warnings FROM {$table} WHERE event_id = %d AND import_job_id = %d AND row_status = %s ORDER BY source_row_number ASC FOR UPDATE", [$scope->eventId, $jobId, ImportRowStatus::PENDING->value]);
        return array_map(fn (array $row): ImportRowRecord => $this->row($row), $rows);
    }

    public function storeValidation(ImportRowRecord $row, ImportRowStatus $status, ?array $normalized, array $errors, array $warnings, DateTimeImmutable $now): void
    {
        $table = $this->table(TableName::IMPORT_ROWS); $normalizedSql = $normalized === null ? 'NULL' : '%s'; $errorsSql = $errors === [] ? 'NULL' : '%s'; $warningsSql = $warnings === [] ? 'NULL' : '%s'; $parameters = [$status->value]; if ($normalized !== null) $parameters[] = $this->json($normalized); if ($errors !== []) $parameters[] = $this->json($errors); if ($warnings !== []) $parameters[] = $this->json($warnings); $parameters[] = $this->time($now); $parameters[] = $row->rowId; $parameters[] = ImportRowStatus::PENDING->value;
        if ($this->database->execute("UPDATE {$table} SET row_status = %s, normalized_data = {$normalizedSql}, validation_errors = {$errorsSql}, validation_warnings = {$warningsSql}, updated_at = %s WHERE import_row_id = %d AND row_status = %s", $parameters) !== 1) throw new PersistenceException('import_row_validation_conflict');
    }

    public function finishValidation(ImportJobRecord $job, int $validRows, int $invalidRows, int $warningRows, array $mapping, DateTimeImmutable $now): ImportJobRecord
    {
        $table = $this->table(TableName::IMPORT_JOBS); if ($this->database->execute("UPDATE {$table} SET import_status = %s, mapping_definition = %s, valid_rows = %d, invalid_rows = %d, warning_rows = %d, validated_at = %s, import_revision=import_revision+1, updated_at = %s WHERE event_id = %d AND import_job_id = %d AND import_revision=%d AND import_status = %s", [ImportStatus::VALIDATED->value, $this->json($mapping), $validRows, $invalidRows, $warningRows, $this->time($now), $this->time($now), $job->eventScope->eventId, $job->jobId,$job->revision, ImportStatus::STAGED->value]) !== 1) throw new PersistenceException('import_validation_conflict');
        return new ImportJobRecord($job->jobId, $job->eventScope, ImportStatus::VALIDATED, $job->sourceFilename, $job->sourceFileHash, $job->totalRows, $validRows, $invalidRows, 0, 0,revision:$job->revision+1,warningRows:$warningRows,mapping:$mapping,uploadedAt:$job->uploadedAt,validatedAt:$now);
    }

    public function acquireLease(EventScope $scope, int $jobId, string $owner, string $token, DateTimeImmutable $now, DateTimeImmutable $expiresAt): ?ImportJobRecord
    {
        $table = $this->table(TableName::IMPORT_JOBS); $affected = $this->database->execute("UPDATE {$table} SET import_status = %s, worker_lease_token = %s, worker_lease_owner = %s, worker_lease_expires_at = %s, worker_heartbeat_at = %s,import_revision=import_revision+1, updated_at = %s WHERE event_id = %d AND import_job_id = %d AND import_status IN (%s, %s) AND (worker_lease_token IS NULL OR worker_lease_expires_at <= %s)", [ImportStatus::APPLYING->value, $token, $owner, $this->time($expiresAt), $this->time($now), $this->time($now), $scope->eventId, $jobId, ImportStatus::VALIDATED->value, ImportStatus::APPLYING->value, $this->time($now)]);
        if ($affected !== 1) return null; $job = $this->lockJob($scope, $jobId); if ($job === null) throw new PersistenceException('import_lease_job_missing'); return $job;
    }

    public function heartbeat(ImportJobRecord $job, string $token, DateTimeImmutable $now, DateTimeImmutable $expiresAt): void
    {
        $table = $this->table(TableName::IMPORT_JOBS); if ($this->database->execute("UPDATE {$table} SET worker_heartbeat_at = %s, worker_lease_expires_at = %s, updated_at = %s WHERE event_id = %d AND import_job_id = %d AND import_status = %s AND worker_lease_token = %s", [$this->time($now), $this->time($expiresAt), $this->time($now), $job->eventScope->eventId, $job->jobId, ImportStatus::APPLYING->value, $token]) !== 1) throw new PersistenceException('import_worker_lease_lost');
    }

    public function readyBatch(ImportJobRecord $job, string $token, DateTimeImmutable $now, int $limit): array
    {
        $jobs = $this->table(TableName::IMPORT_JOBS); if ((int) $this->database->fetchValue("SELECT EXISTS(SELECT 1 FROM {$jobs} WHERE event_id = %d AND import_job_id = %d AND import_status = %s AND worker_lease_token = %s AND worker_lease_expires_at > %s)", [$job->eventScope->eventId, $job->jobId, ImportStatus::APPLYING->value, $token, $this->time($now)]) !== 1) throw new PersistenceException('import_worker_lease_lost');
        $rows = $this->table(TableName::IMPORT_ROWS); return array_map(fn (array $row): ImportRowRecord => $this->row($row), $this->database->fetchAll("SELECT import_row_id, import_job_id, source_row_number, row_status, raw_data, normalized_data, validation_errors, validation_warnings FROM {$rows} WHERE event_id = %d AND import_job_id = %d AND row_status = %s ORDER BY source_row_number ASC LIMIT %d", [$job->eventScope->eventId, $job->jobId, ImportRowStatus::READY->value, $limit]));
    }

    public function markApplied(ImportRowRecord $row, int $invitationId, DateTimeImmutable $now): void { $this->mark($row, ImportRowStatus::APPLIED, $invitationId, null, $now); }
    public function markFailed(ImportRowRecord $row, string $safeCode, DateTimeImmutable $now): void { $this->mark($row, ImportRowStatus::FAILED, null, $safeCode, $now); }

    public function reconcile(ImportJobRecord $job, string $token, DateTimeImmutable $now): ImportJobRecord
    {
        $rows = $this->table(TableName::IMPORT_ROWS); $applied = (int) $this->database->fetchValue("SELECT COUNT(*) FROM {$rows} WHERE event_id = %d AND import_job_id = %d AND row_status = %s", [$job->eventScope->eventId, $job->jobId, ImportRowStatus::APPLIED->value]); $failed = (int) $this->database->fetchValue("SELECT COUNT(*) FROM {$rows} WHERE event_id = %d AND import_job_id = %d AND row_status = %s", [$job->eventScope->eventId, $job->jobId, ImportRowStatus::FAILED->value]); $remaining = (int) $this->database->fetchValue("SELECT COUNT(*) FROM {$rows} WHERE event_id = %d AND import_job_id = %d AND row_status = %s", [$job->eventScope->eventId, $job->jobId, ImportRowStatus::READY->value]); $status = $remaining === 0 ? ImportStatus::COMPLETED : ImportStatus::APPLYING;
        $jobs = $this->table(TableName::IMPORT_JOBS); $completedSql = $status === ImportStatus::COMPLETED ? '%s' : 'NULL'; $parameters = [$status->value, $applied, $failed]; if ($status === ImportStatus::COMPLETED) $parameters[] = $this->time($now); array_push($parameters, $this->time($now), $job->eventScope->eventId, $job->jobId, ImportStatus::APPLYING->value, $token);
        if ($this->database->execute("UPDATE {$jobs} SET import_status = %s, applied_rows = %d, failed_rows = %d, completed_at = {$completedSql}, worker_lease_token = NULL, worker_lease_owner = NULL, worker_lease_expires_at = NULL, worker_heartbeat_at = NULL,import_revision=import_revision+1, updated_at = %s WHERE event_id = %d AND import_job_id = %d AND import_status = %s AND worker_lease_token = %s", $parameters) !== 1) throw new PersistenceException('import_reconcile_conflict');
        return new ImportJobRecord($job->jobId, $job->eventScope, $status, $job->sourceFilename, $job->sourceFileHash, $job->totalRows, $job->validRows, $job->invalidRows, $applied, $failed,revision:$job->revision+1,warningRows:$job->warningRows,skippedRows:$job->skippedRows,mapping:$job->mapping,uploadedAt:$job->uploadedAt,validatedAt:$job->validatedAt,completedAt:$status===ImportStatus::COMPLETED?$now:$job->completedAt);
    }

    private function mark(ImportRowRecord $row, ImportRowStatus $status, ?int $invitationId, ?string $error, DateTimeImmutable $now): void { $table = $this->table(TableName::IMPORT_ROWS); $idSql = $invitationId === null ? 'NULL' : '%d'; $errorSql = $error === null ? 'validation_errors' : '%s'; $appliedSql = $status === ImportRowStatus::APPLIED ? '%s' : 'NULL'; $parameters = [$status->value]; if ($invitationId !== null) $parameters[] = $invitationId; if ($error !== null) $parameters[] = $this->json([$error]); if ($status === ImportRowStatus::APPLIED) $parameters[] = $this->time($now); $parameters[] = $this->time($now); $parameters[] = $row->rowId; $parameters[] = ImportRowStatus::READY->value; if ($this->database->execute("UPDATE {$table} SET row_status = %s, applied_invitation_id = {$idSql}, validation_errors = {$errorSql}, applied_at = {$appliedSql}, updated_at = %s WHERE import_row_id = %d AND row_status = %s", $parameters) !== 1) throw new PersistenceException('import_row_apply_conflict'); }
    /** @param array<string,mixed> $row */
    private function job(array $row, EventScope $scope): ImportJobRecord { $status = ImportStatus::tryFrom((string) ($row['import_status'] ?? '')); if ($status === null || (int) ($row['event_id'] ?? 0) !== $scope->eventId) throw new PersistenceException('import_job_invalid'); return new ImportJobRecord((int) $row['import_job_id'], $scope, $status, (string) $row['source_filename'], (string) $row['source_file_hash'], (int) $row['total_rows'], (int) $row['valid_rows'], (int) $row['invalid_rows'], (int) $row['applied_rows'], (int) $row['failed_rows'], $row['worker_lease_token'] ?? null, $this->date($row['worker_lease_expires_at'] ?? null),(int)($row['import_revision']??1),(int)($row['warning_rows']??0),(int)($row['skipped_rows']??0),isset($row['mapping_definition'])?$this->decode($row['mapping_definition']):[],$this->date($row['uploaded_at']??null),$this->date($row['validated_at']??null),$this->date($row['completed_at']??null),$this->date($row['cancelled_at']??null)); }
    private function jobColumns():string{return'import_job_id,event_id,import_status,import_revision,source_filename,source_file_hash,mapping_definition,total_rows,valid_rows,warning_rows,invalid_rows,applied_rows,skipped_rows,failed_rows,uploaded_at,validated_at,completed_at,cancelled_at,worker_lease_token,worker_lease_expires_at';}
    private function copy(ImportJobRecord$j,ImportStatus$status,int$revision,?DateTimeImmutable$cancelled=null):ImportJobRecord{return new ImportJobRecord($j->jobId,$j->eventScope,$status,$j->sourceFilename,$j->sourceFileHash,$j->totalRows,$j->validRows,$j->invalidRows,$j->appliedRows,$j->failedRows,$j->leaseToken,$j->leaseExpiresAt,$revision,$j->warningRows,$j->skippedRows,$j->mapping,$j->uploadedAt,$j->validatedAt,$j->completedAt,$cancelled??$j->cancelledAt);}
    /** @param array<string,mixed> $row */
    private function row(array $row): ImportRowRecord { $status = ImportRowStatus::tryFrom((string) ($row['row_status'] ?? '')); if ($status === null) throw new PersistenceException('import_row_invalid'); return new ImportRowRecord((int) $row['import_row_id'], (int) $row['import_job_id'], (int) $row['source_row_number'], $status, $this->decode($row['raw_data'] ?? null), isset($row['normalized_data']) ? $this->decode($row['normalized_data']) : null, isset($row['validation_errors']) ? array_values($this->decode($row['validation_errors'])) : [], isset($row['validation_warnings']) ? array_values($this->decode($row['validation_warnings'])) : []); }
    private function json(array $value): string { return json_encode($value, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); }
    /** @return array<string,mixed> */ private function decode(mixed $value): array { $decoded = json_decode((string) $value, true, 32, JSON_THROW_ON_ERROR); if (!is_array($decoded)) throw new PersistenceException('import_json_invalid'); return $decoded; }
    private function time(DateTimeImmutable $date): string { return $date->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s'); }
    private function date(mixed $value): ?DateTimeImmutable { return $value === null ? null : new DateTimeImmutable((string) $value, new DateTimeZone('UTC')); }
}
