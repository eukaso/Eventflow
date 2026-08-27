<?php

namespace EventFlow\Infrastructure\Persistence\WordPress;

use DateTimeImmutable;
use DateTimeZone;
use EventFlow\Application\Attendee\{AttendanceStatus, AttendeeRecord, AttendeeRole, InvitationResponseStatus, RsvpInvitation, RsvpResult};
use EventFlow\Application\GuestAccess\{GuestInvitationContext, GuestSessionAccessRepository};
use EventFlow\Application\Invitation\InvitationStatus;
use EventFlow\Application\Persistence\EventScope;
use EventFlow\Infrastructure\Persistence\{PersistenceException, TableName};

final class WpdbGuestSessionAccessRepository extends AbstractWpdbRepository implements GuestSessionAccessRepository
{
    public function findContext(EventScope $scope, int $invitationId): ?GuestInvitationContext
    {
        if ($invitationId < 1) {
            throw new PersistenceException('invitation_id_invalid');
        }
        $events = $this->table(TableName::EVENTS);
        $configurations = $this->table(TableName::EVENT_CONFIGURATIONS);
        $invitations = $this->table(TableName::INVITATIONS);
        $row = $this->database->fetchRow(
            'SELECT e.event_id,e.event_name,e.timezone,e.starts_at,e.ends_at,' .
            'v.venue_name,v.address_line_1,v.address_line_2,v.city,v.region,v.postal_code,v.country_code,' .
            'i.invitation_id,i.primary_name,i.primary_email,i.primary_phone,i.capacity,i.response_status,i.response_revision,' .
            'c.allow_guest_edits,c.welcome_message,c.confirmation_message,c.surprise_notice,c.dress_code,c.confirmation_opens_at,c.confirmation_closes_at ' .
            "FROM {$invitations} i INNER JOIN {$events} e ON e.event_id=i.event_id " .
            'LEFT JOIN ' . $this->table(TableName::VENUES) . ' v ON v.venue_id=e.venue_id AND v.deleted_at IS NULL ' .
            "INNER JOIN {$configurations} c ON c.event_id=i.event_id " .
            'WHERE i.event_id=%d AND i.invitation_id=%d AND i.invitation_status=%s AND i.deleted_at IS NULL AND e.deleted_at IS NULL LIMIT 1',
            [$scope->eventId, $invitationId, InvitationStatus::ACTIVE->value],
        );
        if ($row === null) {
            return null;
        }
        $response = InvitationResponseStatus::tryFrom((string) ($row['response_status'] ?? ''));
        if ($response === null || (int) ($row['event_id'] ?? 0) !== $scope->eventId) {
            throw new PersistenceException('guest_context_invalid');
        }
        $collectRequirements = !$this->usesCompactLui60Rsvp((string) ($row['event_name'] ?? ''));
        return new GuestInvitationContext(
            $scope,
            (int) ($row['invitation_id'] ?? 0),
            (string) ($row['event_name'] ?? ''),
            (string) ($row['timezone'] ?? ''),
            $this->date($row['starts_at'] ?? null),
            $this->date($row['ends_at'] ?? null),
            (string) ($row['primary_name'] ?? ''),
            (int) ($row['capacity'] ?? 0),
            $response,
            (int) ($row['response_revision'] ?? -1),
            (bool) (int) ($row['allow_guest_edits'] ?? 0),
            $this->nullableString($row['welcome_message'] ?? null),
            $this->nullableString($row['confirmation_message'] ?? null),
            $this->nullableString($row['surprise_notice'] ?? null),
            $this->nullableString($row['dress_code'] ?? null),
            $this->date($row['confirmation_opens_at'] ?? null),
            $this->date($row['confirmation_closes_at'] ?? null),
            $this->nullableString($row['primary_email'] ?? null),
            $this->nullableString($row['primary_phone'] ?? null),
            $collectRequirements,
            $collectRequirements,
            $this->nullableString($row['venue_name'] ?? null),
            $this->venueAddress($row),
        );
    }

    public function findResponse(EventScope $scope, int $invitationId): ?RsvpResult
    {
        if ($invitationId < 1) {
            throw new PersistenceException('invitation_id_invalid');
        }
        $invitations = $this->table(TableName::INVITATIONS);
        $row = $this->database->fetchRow(
            "SELECT invitation_id,event_id,capacity,invitation_status,response_status,response_revision FROM {$invitations} " .
            'WHERE event_id=%d AND invitation_id=%d AND invitation_status=%s AND deleted_at IS NULL LIMIT 1',
            [$scope->eventId, $invitationId, InvitationStatus::ACTIVE->value],
        );
        if ($row === null) {
            return null;
        }
        $status = InvitationStatus::tryFrom((string) ($row['invitation_status'] ?? ''));
        $response = InvitationResponseStatus::tryFrom((string) ($row['response_status'] ?? ''));
        if ($status === null || $response === null || (int) ($row['event_id'] ?? 0) !== $scope->eventId) {
            throw new PersistenceException('guest_response_invalid');
        }
        $invitation = new RsvpInvitation(
            (int) ($row['invitation_id'] ?? 0),
            $scope,
            (int) ($row['capacity'] ?? 0),
            $status,
            $response,
            (int) ($row['response_revision'] ?? -1),
        );
        if ($response === InvitationResponseStatus::DECLINED) {
            return new RsvpResult($invitation, []);
        }
        $attendees = $this->table(TableName::ATTENDEES);
        $rows = $this->database->fetchAll(
            "SELECT attendee_id,event_id,invitation_id,display_name,attendee_role,attendance_status,email,phone,dietary_requirements,accessibility_requirements FROM {$attendees} " .
            'WHERE event_id=%d AND invitation_id=%d AND attendance_status IN (%s,%s) AND deleted_at IS NULL ORDER BY attendee_id ASC',
            [$scope->eventId, $invitationId, AttendanceStatus::PENDING->value, AttendanceStatus::CONFIRMED->value],
        );
        return new RsvpResult(
            $invitation,
            array_map(fn (array $attendee): AttendeeRecord => $this->attendee($scope, $invitationId, $attendee), $rows),
        );
    }

    public function revokeSession(int $sessionId, EventScope $scope, int $invitationId, DateTimeImmutable $now): void
    {
        if ($sessionId < 1 || $invitationId < 1) {
            throw new PersistenceException('guest_session_invalid');
        }
        $sessions = $this->table(TableName::GUEST_SESSIONS);
        $timestamp = $this->timestamp($now);
        if ($this->database->execute(
            "UPDATE {$sessions} SET session_status=%s,revoked_at=%s,updated_at=%s " .
            'WHERE guest_session_id=%d AND event_id=%d AND invitation_id=%d AND session_status=%s',
            ['revoked', $timestamp, $timestamp, $sessionId, $scope->eventId, $invitationId, 'active'],
        ) !== 1) {
            throw new PersistenceException('guest_session_invalid');
        }
    }

    /** @param array<string, mixed> $row */
    private function attendee(EventScope $scope, int $invitationId, array $row): AttendeeRecord
    {
        $role = AttendeeRole::tryFrom((string) ($row['attendee_role'] ?? ''));
        $status = AttendanceStatus::tryFrom((string) ($row['attendance_status'] ?? ''));
        if (
            $role === null
            || $status === null
            || (int) ($row['event_id'] ?? 0) !== $scope->eventId
            || (int) ($row['invitation_id'] ?? 0) !== $invitationId
        ) {
            throw new PersistenceException('guest_attendee_invalid');
        }
        return new AttendeeRecord(
            (int) ($row['attendee_id'] ?? 0),
            $scope,
            $invitationId,
            (string) ($row['display_name'] ?? ''),
            $role,
            $status,
            $this->nullableString($row['email'] ?? null),
            $this->nullableString($row['phone'] ?? null),
            $this->nullableString($row['dietary_requirements'] ?? null),
            $this->nullableString($row['accessibility_requirements'] ?? null),
        );
    }

    private function nullableString(mixed $value): ?string
    {
        return $value === null ? null : (string) $value;
    }

    private function timestamp(DateTimeImmutable $date): string
    {
        return $date->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s');
    }

    private function date(mixed $value): ?DateTimeImmutable
    {
        return $value === null ? null : new DateTimeImmutable((string) $value, new DateTimeZone('UTC'));
    }

    private function usesCompactLui60Rsvp(string $eventName): bool
    {
        return str_starts_with(strtolower(trim($eventName)), 'lui @ 60 reference reconciliation');
    }

    /** @param array<string, mixed> $row */
    private function venueAddress(array $row): ?string
    {
        $parts = array_values(array_filter(array_map(
            fn (string $field): ?string => $this->nullableString($row[$field] ?? null),
            ['address_line_1', 'address_line_2', 'city', 'region', 'postal_code', 'country_code'],
        ), static fn (?string $value): bool => $value !== null && trim($value) !== ''));

        return $parts === [] ? null : implode(', ', $parts);
    }
}
