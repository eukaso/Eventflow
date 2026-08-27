<?php

namespace EventFlow\Presentation\Api;

use DateTimeImmutable;
use DateTimeZone;
use EventFlow\Application\Attendee\{AttendeeRecord, RsvpResult};
use EventFlow\Application\Error\RequestId;
use EventFlow\Application\GuestAccess\GuestInvitationContext;

final readonly class GuestSessionAccessPresenter
{
    public function context(GuestInvitationContext $context, RequestId $requestId): JsonApiResponse
    {
        return new JsonApiResponse(200, [
            'data' => [
                'event_id' => $context->eventScope->eventId,
                'invitation_id' => $context->invitationId,
                'event_name' => $context->eventName,
                'timezone' => $context->timezone,
                'starts_at' => $this->date($context->startsAt),
                'ends_at' => $this->date($context->endsAt),
                'starts_at_display' => $this->eventDate($context->startsAt, $context->timezone),
                'ends_at_display' => $this->eventDate($context->endsAt, $context->timezone),
                'venue_name' => $context->venueName,
                'venue_address' => $context->venueAddress,
                'primary_name' => $context->primaryName,
                'primary_email' => $context->primaryEmail,
                'primary_phone' => $context->primaryPhone,
                'capacity' => $context->capacity,
                'response_status' => $context->responseStatus->value,
                'response_revision' => $context->responseRevision,
                'allow_guest_edits' => $context->allowGuestEdits,
                'welcome_message' => $context->welcomeMessage,
                'confirmation_message' => $context->confirmationMessage,
                'surprise_notice' => $context->surpriseNotice,
                'dress_code' => $context->dressCode,
                'confirmation_opens_at' => $this->date($context->confirmationOpensAt),
                'confirmation_closes_at' => $this->date($context->confirmationClosesAt),
                'collect_dietary_requirements' => $context->collectDietaryRequirements,
                'collect_accessibility_requirements' => $context->collectAccessibilityRequirements,
            ],
            'request_id' => $requestId->value,
        ], $this->headers($requestId));
    }

    public function response(RsvpResult $result, RequestId $requestId): JsonApiResponse
    {
        $headers = $this->headers($requestId);
        $headers['ETag'] = '"' . $result->invitation->responseRevision . '"';
        return new JsonApiResponse(200, [
            'data' => [
                'invitation_id' => $result->invitation->invitationId,
                'response_status' => $result->invitation->responseStatus->value,
                'response_revision' => $result->invitation->responseRevision,
                'capacity' => $result->invitation->capacity,
                'attendees' => array_map($this->attendee(...), $result->attendees),
            ],
            'request_id' => $requestId->value,
        ], $headers);
    }

    public function logout(RequestId $requestId): JsonApiResponse
    {
        $headers = $this->headers($requestId);
        $headers['Set-Cookie'] = GuestSessionCookie::NAME
            . '=; Expires=Thu, 01 Jan 1970 00:00:00 GMT; Max-Age=0'
            . '; Path=' . GuestSessionCookie::PATH . '; Secure; HttpOnly; SameSite=Lax';
        return new JsonApiResponse(204, [], $headers);
    }

    /** @return array<string, string> */
    private function headers(RequestId $requestId): array
    {
        return ['X-Request-ID' => $requestId->value, 'Cache-Control' => 'no-store, max-age=0', 'Pragma' => 'no-cache'];
    }

    private function date(?DateTimeImmutable $date): ?string
    {
        return $date?->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d\TH:i:s\Z');
    }

    private function eventDate(?DateTimeImmutable $date, string $timezone): ?string
    {
        if ($date === null) {
            return null;
        }

        return $date->setTimezone(new DateTimeZone($timezone))->format('l, F j, Y \a\t g:i A');
    }

    /** @return array<string, mixed> */
    private function attendee(AttendeeRecord $attendee): array
    {
        return [
            'id' => $attendee->attendeeId,
            'display_name' => $attendee->displayName,
            'role' => $attendee->role->value,
            'status' => $attendee->status->value,
            'email' => $attendee->email,
            'phone' => $attendee->phone,
            'dietary_requirements' => $attendee->dietaryRequirements,
            'accessibility_requirements' => $attendee->accessibilityRequirements,
        ];
    }
}
