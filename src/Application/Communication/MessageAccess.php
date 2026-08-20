<?php
namespace EventFlow\Application\Communication;
use EventFlow\Application\Authorization\PrincipalContext;use EventFlow\Application\Idempotency\IdempotencyOutcome;use EventFlow\Application\Persistence\EventScope;
interface MessageAccess{public function list(PrincipalContext $principal,EventScope $scope,int $limit=50,?int $afterMessageId=null,?int $campaignId=null,?string $status=null):MessagePage;public function read(PrincipalContext $principal,EventScope $scope,int $messageId):MessageRecord;public function retry(PrincipalContext $principal,EventScope $scope,int $messageId,int $expectedRevision,string $idempotencyKey):IdempotencyOutcome;}
