<?php

namespace EventFlow\Application\Event;

use DateTimeImmutable;
use DateTimeZone;
use InvalidArgumentException;

final readonly class CreateEvent
{
    public function __construct(
        public string $name,
        public string $slug,
        public string $timezone,
        public ?DateTimeImmutable $startsAt = null,
        public ?DateTimeImmutable $endsAt = null,
        public ?int $venueId = null,
    ) {
        if ($name === '' || strlen($name) > 190) {
            throw new InvalidArgumentException('event_name_invalid');
        }
        if (!preg_match('/^[a-z0-9](?:[a-z0-9-]{0,188}[a-z0-9])?$/', $slug)) {
            throw new InvalidArgumentException('event_slug_invalid');
        }
        if (!in_array($timezone, DateTimeZone::listIdentifiers(), true)) {
            throw new InvalidArgumentException('event_timezone_invalid');
        }
        if ($startsAt !== null && $endsAt !== null && $endsAt <= $startsAt) {
            throw new InvalidArgumentException('event_dates_invalid');
        }
        if ($venueId !== null && $venueId < 1) {
            throw new InvalidArgumentException('event_venue_invalid');
        }
    }

    /** @return array<string, mixed> */
    public function canonicalRequest(): array
    {
        return [
            'name' => $this->name,
            'slug' => $this->slug,
            'timezone' => $this->timezone,
            'starts_at' => $this->startsAt?->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d\TH:i:s\Z'),
            'ends_at' => $this->endsAt?->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d\TH:i:s\Z'),
            'venue_id' => $this->venueId,
        ];
    }
}
