<?php

namespace EventFlow\Application\Privacy;

final readonly class PrivacyActionPage
{
    /** @param list<PrivacyActionRecord> $actions */
    public function __construct(public array $actions, public ?int $nextAfterActionId) {}
}
