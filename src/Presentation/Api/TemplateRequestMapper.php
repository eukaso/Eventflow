<?php

namespace EventFlow\Presentation\Api;

use EventFlow\Application\Communication\CommunicationChannel;
use EventFlow\Application\Persistence\EventScope;

final readonly class TemplateRequestMapper
{
    public function draft(RestRequest $request): TemplateDraftInput
    {
        $json = $this->only($request, ['key', 'name', 'channel', 'type', 'subject', 'body', 'plain_text', 'allowed_fields']);
        $channel = is_string($json['channel'] ?? null) ? CommunicationChannel::tryFrom($json['channel']) : null;
        $allowed = $json['allowed_fields'] ?? [];
        if ($channel === null || !is_array($allowed) || !array_is_list($allowed)) throw new RequestInputException('validation_failed');
        $fields = [];
        foreach ($allowed as $field) $fields[] = $this->requiredString($field);
        return new TemplateDraftInput(
            $this->scope($request),
            $this->requiredString($json['key'] ?? null),
            $this->requiredString($json['name'] ?? null),
            $channel,
            $this->requiredString($json['type'] ?? null),
            $this->optionalString($json['subject'] ?? null),
            $this->requiredString($json['body'] ?? null, false),
            $this->optionalString($json['plain_text'] ?? null, false),
            $fields,
        );
    }

    /** @return array{scope: EventScope, template_id: int} */
    public function publication(RestRequest $request): array
    {
        $this->only($request, []);
        return ['scope' => $this->scope($request), 'template_id' => $this->routeId($request, 'template_id')];
    }

    private function scope(RestRequest $request): EventScope { return new EventScope($this->routeId($request, 'event_id')); }

    private function routeId(RestRequest $request, string $name): int
    {
        $candidate = $request->route($name);
        if ($candidate === null || !ctype_digit($candidate)) throw new RequestInputException('resource_not_found');
        $value = filter_var($candidate, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        if ($value === false) throw new RequestInputException('resource_not_found');
        return $value;
    }

    /** @param list<string> $allowed @return array<string, mixed> */
    private function only(RestRequest $request, array $allowed): array
    {
        $json = $request->json();
        if (array_diff(array_keys($json), $allowed) !== []) throw new RequestInputException('validation_failed');
        return $json;
    }

    private function requiredString(mixed $value, bool $trim = true): string
    {
        if (!is_string($value)) throw new RequestInputException('validation_failed');
        return $trim ? trim($value) : $value;
    }

    private function optionalString(mixed $value, bool $trim = true): ?string
    {
        if ($value === null) return null;
        return $this->requiredString($value, $trim);
    }
}
