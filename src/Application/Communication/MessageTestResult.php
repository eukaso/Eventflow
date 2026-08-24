<?php

namespace EventFlow\Application\Communication;

final readonly class MessageTestResult
{
    public function __construct(public MessageRecord $message, public int $jobId) {}
}
