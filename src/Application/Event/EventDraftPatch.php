<?php

namespace EventFlow\Application\Event;

use DateTimeImmutable;
use DateTimeZone;
use InvalidArgumentException;

final readonly class EventDraftPatch
{
    private const FIELDS = ['name', 'slug', 'timezone', 'starts_at', 'ends_at', 'venue_id'];

    /** @param array<string, mixed> $changes */
    public function __construct(
        public array $changes,
        public int $expectedRevision,
    ) {
        if ($expectedRevision < 1) {
            throw new InvalidArgumentException('event_revision_invalid');
        }
        if ($changes === [] || array_diff(array_keys($changes), self::FIELDS) !== []) {
            throw new InvalidArgumentException('event_patch_invalid');
        }
        foreach (['name', 'slug', 'timezone'] as $field) {
            if (array_key_exists($field, $changes) && !is_string($changes[$field])) {
                throw new InvalidArgumentException('event_patch_invalid');
            }
        }
        foreach (['starts_at', 'ends_at'] as $field) {
            if (array_key_exists($field, $changes) && $changes[$field] !== null && !$changes[$field] instanceof DateTimeImmutable) {
                throw new InvalidArgumentException('event_patch_invalid');
            }
        }
        if (array_key_exists('venue_id', $changes) && $changes['venue_id'] !== null && !is_int($changes['venue_id'])) {
            throw new InvalidArgumentException('event_patch_invalid');
        }
    }

    public function applyTo(EventRecord $current): CreateEvent
    {
        return new CreateEvent(
            $this->changes['name'] ?? $current->name,
            $this->changes['slug'] ?? $current->slug,
            $this->changes['timezone'] ?? $current->timezone,
            array_key_exists('starts_at', $this->changes) ? $this->changes['starts_at'] : $current->startsAt,
            array_key_exists('ends_at', $this->changes) ? $this->changes['ends_at'] : $current->endsAt,
            array_key_exists('venue_id', $this->changes) ? $this->changes['venue_id'] : $current->venueId,
        );
    }

    /** @return array<string, mixed> */
    public function canonicalRequest(): array
    {
        $canonical = ['expected_revision' => $this->expectedRevision];
        foreach ($this->changes as $field => $value) {
            $canonical[$field] = $value instanceof DateTimeImmutable
                ? $value->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d\TH:i:s\Z')
                : $value;
        }
        ksort($canonical);
        return $canonical;
    }
}
