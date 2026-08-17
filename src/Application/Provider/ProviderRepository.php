<?php
namespace EventFlow\Application\Provider;
use DateTimeImmutable;use EventFlow\Application\Persistence\EventScope;
interface ProviderRepository{public function lockQueuedMessage(EventScope $scope,int $messageId,string $provider,DateTimeImmutable $now):ProviderDispatchMessage;public function recordSendResult(ProviderDispatchMessage $message,string $provider,ProviderSendResult $result,DateTimeImmutable $now):void;public function correlate(EventScope $scope,string $provider,?string $providerMessageId,?string $providerRequestId):?array;public function storeEvent(NormalizedProviderWebhook $event,int $messageId,?int $attemptId,DateTimeImmutable $receivedAt):ProviderEventRecord;public function applyEvent(EventScope $scope,ProviderEventRecord $event,DateTimeImmutable $processedAt):void;}
