<?php
namespace EventFlow\Application\Communication;
use DateTimeImmutable;use EventFlow\Application\Persistence\EventScope;
interface MessageAccessRepository{public function listMessages(EventScope $scope,int $limit,?int $afterMessageId,?int $campaignId,?string $status):MessagePage;public function findMessage(EventScope $scope,int $messageId):?MessageRecord;public function createTestMessage(EventScope $scope,CommunicationChannel $channel,string $recipientName,string $recipientAddress,?string $subject,string $content,?string $plainText,string $logicalKey,DateTimeImmutable $now):MessageRecord;public function lockMessage(EventScope $scope,int $messageId):?MessageRecord;public function markRetryPending(EventScope $scope,MessageRecord $current,DateTimeImmutable $now):MessageRecord;}
