<?php
namespace EventFlow\Application\Communication;
final readonly class MessagePage{/** @param list<MessageRecord> $messages */public function __construct(public array $messages,public ?int $nextAfterMessageId){}}
