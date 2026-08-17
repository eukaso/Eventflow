<?php

namespace EventFlow\Presentation\Api;

use EventFlow\Application\Attendee\{AttendeeRole, DesiredAttendee, InvitationResponseStatus, SubmitRsvp};
use EventFlow\Application\Authorization\PrincipalContext;
use InvalidArgumentException;

final readonly class RsvpRequestMapper
{
    public function submit(RestRequest $request, PrincipalContext $principal, int $expectedRevision): SubmitRsvp
    {
        $json = $request->json();
        if (array_diff(array_keys($json), ['response_status', 'attendees']) !== []) throw new RequestInputException('validation_failed');
        $scope = $principal->eventScope;
        $invitationId = $principal->invitationId;
        if ($scope === null || $invitationId === null) throw new RequestInputException('guest_session_invalid');
        $status = is_string($json['response_status'] ?? null)
            ? InvitationResponseStatus::tryFrom($json['response_status'])
            : null;
        $source = $json['attendees'] ?? null;
        if ($status === null || $status === InvitationResponseStatus::PENDING || !is_array($source) || !array_is_list($source) || count($source) > 65535) {
            throw new RequestInputException('validation_failed');
        }
        try {
            $attendees = array_map($this->attendee(...), $source);
            return new SubmitRsvp($scope, $invitationId, $expectedRevision, $status, $attendees);
        } catch (RequestInputException $failure) {
            throw $failure;
        } catch (InvalidArgumentException) {
            throw new RequestInputException('validation_failed');
        }
    }

    private function attendee(mixed $source): DesiredAttendee
    {
        if (!is_array($source) || array_is_list($source)) throw new RequestInputException('validation_failed');
        $allowed = ['attendee_id', 'display_name', 'role', 'email', 'phone', 'dietary_requirements', 'accessibility_requirements'];
        if (array_diff(array_keys($source), $allowed) !== []) throw new RequestInputException('validation_failed');
        $role = is_string($source['role'] ?? null) ? AttendeeRole::tryFrom($source['role']) : null;
        if ($role === null) throw new RequestInputException('validation_failed');
        return new DesiredAttendee(
            $this->requiredString($source['display_name'] ?? null),
            $role,
            $this->optionalPositiveInt($source['attendee_id'] ?? null),
            $this->optionalString($source['email'] ?? null),
            $this->optionalString($source['phone'] ?? null),
            $this->optionalString($source['dietary_requirements'] ?? null),
            $this->optionalString($source['accessibility_requirements'] ?? null),
        );
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

    private function optionalPositiveInt(mixed $value): ?int
    {
        if ($value === null) return null;
        if (!is_int($value) || $value < 1) throw new RequestInputException('validation_failed');
        return $value;
    }
}
