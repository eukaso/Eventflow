<?php
namespace EventFlow\Application\Import;
use DateTimeImmutable;use EventFlow\Application\Persistence\EventScope;
interface ImportAdministrationRepository{public function listJobs(EventScope $scope,int $limit,?int $afterJobId,?ImportStatus $status):ImportJobPage;public function findJob(EventScope $scope,int $jobId):?ImportJobRecord;public function listRows(EventScope $scope,int $jobId,int $limit,?int $afterRowId,?ImportRowStatus $status):ImportRowPage;public function markApplyQueued(EventScope $scope,ImportJobRecord $current,DateTimeImmutable $now):ImportJobRecord;public function cancelJob(EventScope $scope,ImportJobRecord $current,DateTimeImmutable $now):ImportJobRecord;}
