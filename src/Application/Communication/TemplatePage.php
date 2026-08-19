<?php

namespace EventFlow\Application\Communication;

final readonly class TemplatePage
{
    /** @param list<TemplateRecord> $templates */
    public function __construct(public array $templates, public ?int $nextAfterTemplateId) {}
}
