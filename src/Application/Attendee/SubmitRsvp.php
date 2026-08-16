<?php

namespace EventFlow\Application\Attendee;

use EventFlow\Application\Persistence\EventScope;
use InvalidArgumentException;

final readonly class SubmitRsvp
{
    /** @param list<DesiredAttendee> $attendees */
    public function __construct(
        public EventScope $eventScope,
        public int $invitationId,
        public int $expectedRevision,
        public InvitationResponseStatus $responseStatus,
        public array $attendees,
    ) {
        if ($invitationId < 1 || $expectedRevision < 0 || $responseStatus === InvitationResponseStatus::PENDING) {
            throw new InvalidArgumentException('invalid_rsvp_submission');
        }
        foreach ($attendees as $attendee) if (!$attendee instanceof DesiredAttendee) throw new InvalidArgumentException('invalid_rsvp_attendees');
    }

    /** @return array<string, mixed> */
    public function canonicalRequest(): array
    {
        return [
            'event_id' => $this->eventScope->eventId,
            'invitation_id' => $this->invitationId,
            'expected_revision' => $this->expectedRevision,
            'response_status' => $this->responseStatus->value,
            'attendees' => array_map(static fn (DesiredAttendee $attendee): array => $attendee->canonical(), $this->attendees),
        ];
    }
}
