<?php

namespace EventFlow\Application\Privacy;

final readonly class RetentionHoldPage
{
    /** @param list<RetentionHoldRecord> $holds */
    public function __construct(public array $holds, public ?int $nextAfterHoldId) {}
}
