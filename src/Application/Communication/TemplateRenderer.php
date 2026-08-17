<?php

namespace EventFlow\Application\Communication;

final readonly class TemplateRenderer
{
    public const PREVIEW_GUEST_LINK = 'https://example.invalid/eventflow-preview';

    /** @param list<string> $allowed @param array<string,string> $values */
    public function render(string $source, array $allowed, array $values, bool $preview = false): string
    {
        preg_match_all('/\{\{([a-z][a-z0-9_]*)\}\}/', $source, $matches);
        foreach (array_unique($matches[1]) as $field) if (!in_array($field, $allowed, true)) throw new CommunicationException('template_merge_field_invalid');
        if ($preview) $values['guest_link'] = self::PREVIEW_GUEST_LINK;
        return preg_replace_callback('/\{\{([a-z][a-z0-9_]*)\}\}/', static fn (array $m): string => htmlspecialchars($values[$m[1]] ?? '', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'), $source) ?? throw new CommunicationException('template_render_failed');
    }
}
