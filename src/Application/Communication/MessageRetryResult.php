<?php
namespace EventFlow\Application\Communication;
final readonly class MessageRetryResult{public function __construct(public MessageRecord $message,public int $jobId){if($jobId<1)throw new CommunicationException('message_retry_invalid');}}
