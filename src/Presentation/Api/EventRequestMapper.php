<?php

namespace EventFlow\Presentation\Api;

use DateTimeImmutable;
use EventFlow\Application\Event\CreateEvent;
use EventFlow\Application\Persistence\EventScope;
use Exception;
use InvalidArgumentException;

final readonly class EventRequestMapper
{
    public function create(RestRequest $request): CreateEvent
    {
        $json = $request->json();
        $allowed = ['name', 'slug', 'timezone', 'starts_at', 'ends_at', 'venue_id'];
        if (array_diff(array_keys($json), $allowed) !== []) {
            throw new RequestInputException('validation_failed');
        }
        try {
            return new CreateEvent(
                $this->requiredString($json, 'name'),
                $this->requiredString($json, 'slug'),
                $this->requiredString($json, 'timezone'),
                $this->date($json['starts_at'] ?? null),
                $this->date($json['ends_at'] ?? null),
                $this->optionalPositiveInt($json['venue_id'] ?? null),
            );
        } catch (RequestInputException $failure) {
            throw $failure;
        } catch (InvalidArgumentException) {
            throw new RequestInputException('validation_failed');
        }
    }

    public function scope(RestRequest $request): EventScope
    {
        $eventId = $request->route('event_id');
        if ($eventId === null || !ctype_digit($eventId)) {
            throw new RequestInputException('resource_not_found');
        }
        $value = filter_var($eventId, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        if ($value === false) {
            throw new RequestInputException('resource_not_found');
        }
        return new EventScope($value);
    }

    /** @param array<string, mixed> $json */
    private function requiredString(array $json, string $field): string
    {
        $value = $json[$field] ?? null;
        if (!is_string($value)) throw new RequestInputException('validation_failed');
        return trim($value);
    }

    private function date(mixed $value): ?DateTimeImmutable
    {
        if ($value === null) return null;
        if (!is_string($value) || !preg_match('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}(?:Z|[+-]\d{2}:\d{2})$/', $value)) {
            throw new RequestInputException('validation_failed');
        }
        try { $date = new DateTimeImmutable($value); } catch (Exception) { throw new RequestInputException('validation_failed'); }
        $canonicalInput = str_ends_with($value, 'Z') ? substr($value, 0, -1) . '+00:00' : $value;
        if ($date->format('Y-m-d\TH:i:sP') !== $canonicalInput) throw new RequestInputException('validation_failed');
        return $date;
    }

    private function optionalPositiveInt(mixed $value): ?int
    {
        if ($value === null) return null;
        if (!is_int($value) || $value < 1) throw new RequestInputException('validation_failed');
        return $value;
    }
}
