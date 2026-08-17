<?php

namespace EventFlow\Presentation\Api;

use EventFlow\Application\Attendee\{AttendeeRole, DesiredAttendee};
use EventFlow\Application\Persistence\EventScope;
use InvalidArgumentException;

final readonly class AttendeeRequestMapper
{
    private const ATTENDEE_FIELDS = [
        'display_name', 'role', 'email', 'phone', 'dietary_requirements', 'accessibility_requirements',
    ];

    public function create(RestRequest $request): AttendeeMutation
    {
        $json = $this->only($request, ['invitation_id', ...self::ATTENDEE_FIELDS]);
        return new AttendeeMutation(
            $this->scope($request),
            $this->positiveInt($json['invitation_id'] ?? null),
            $this->desired($json),
        );
    }

    public function update(RestRequest $request): AttendeeMutation
    {
        $json = $this->only($request, ['invitation_id', ...self::ATTENDEE_FIELDS]);
        $attendeeId = $this->attendeeId($request);
        return new AttendeeMutation(
            $this->scope($request),
            $this->positiveInt($json['invitation_id'] ?? null),
            $this->desired($json, $attendeeId),
        );
    }

    public function invitationId(RestRequest $request, bool $allowExpectedPrimary = false): int
    {
        $allowed = $allowExpectedPrimary ? ['invitation_id', 'expected_primary_attendee_id'] : ['invitation_id'];
        $json = $this->only($request, $allowed);
        return $this->positiveInt($json['invitation_id'] ?? null);
    }

    public function expectedPrimaryId(RestRequest $request): int
    {
        return $this->positiveInt($request->json()['expected_primary_attendee_id'] ?? null);
    }

    public function scope(RestRequest $request): EventScope
    {
        return new EventScope($this->routeId($request, 'event_id'));
    }

    public function attendeeId(RestRequest $request): int
    {
        return $this->routeId($request, 'attendee_id');
    }

    /** @param list<string> $allowed @return array<string, mixed> */
    private function only(RestRequest $request, array $allowed): array
    {
        $json = $request->json();
        if (array_diff(array_keys($json), $allowed) !== []) throw new RequestInputException('validation_failed');
        return $json;
    }

    /** @param array<string, mixed> $json */
    private function desired(array $json, ?int $attendeeId = null): DesiredAttendee
    {
        $role = is_string($json['role'] ?? null) ? AttendeeRole::tryFrom($json['role']) : null;
        if ($role === null) throw new RequestInputException('validation_failed');
        try {
            return new DesiredAttendee(
                $this->requiredString($json['display_name'] ?? null),
                $role,
                $attendeeId,
                $this->optionalString($json['email'] ?? null),
                $this->optionalString($json['phone'] ?? null),
                $this->optionalString($json['dietary_requirements'] ?? null),
                $this->optionalString($json['accessibility_requirements'] ?? null),
            );
        } catch (RequestInputException $failure) {
            throw $failure;
        } catch (InvalidArgumentException) {
            throw new RequestInputException('validation_failed');
        }
    }

    private function routeId(RestRequest $request, string $name): int
    {
        $candidate = $request->route($name);
        if ($candidate === null || !ctype_digit($candidate)) throw new RequestInputException('resource_not_found');
        $value = filter_var($candidate, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        if ($value === false) throw new RequestInputException('resource_not_found');
        return $value;
    }

    private function positiveInt(mixed $value): int
    {
        if (!is_int($value) || $value < 1) throw new RequestInputException('validation_failed');
        return $value;
    }

    private function requiredString(mixed $value): string
    {
        if (!is_string($value)) throw new RequestInputException('validation_failed');
        return trim($value);
    }

    private function optionalString(mixed $value): ?string
    {
        if ($value === null) return null;
        if (!is_string($value)) throw new RequestInputException('validation_failed');
        return trim($value);
    }
}
