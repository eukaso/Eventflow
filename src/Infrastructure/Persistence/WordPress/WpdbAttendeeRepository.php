<?php

namespace EventFlow\Infrastructure\Persistence\WordPress;

use DateTimeImmutable;
use DateTimeZone;
use EventFlow\Application\Attendee\AttendanceStatus;
use EventFlow\Application\Attendee\AttendeeRecord;
use EventFlow\Application\Attendee\AttendeeRepository;
use EventFlow\Application\Attendee\AttendeeRole;
use EventFlow\Application\Attendee\DesiredAttendee;
use EventFlow\Application\Attendee\InvitationResponseStatus;
use EventFlow\Application\Attendee\RsvpInvitation;
use EventFlow\Application\Invitation\InvitationStatus;
use EventFlow\Application\Persistence\EventScope;
use EventFlow\Infrastructure\Persistence\PersistenceException;
use EventFlow\Infrastructure\Persistence\TableName;

final class WpdbAttendeeRepository extends AbstractWpdbRepository implements AttendeeRepository
{
    public function lockInvitation(EventScope $scope, int $invitationId): ?RsvpInvitation
    {
        if ($invitationId < 1) throw new PersistenceException('invalid_invitation_id');
        $table = $this->table(TableName::INVITATIONS);
        $row = $this->database->fetchRow(
            "SELECT invitation_id, event_id, capacity, invitation_status, response_status, response_revision FROM {$table} " .
            'WHERE event_id = %d AND invitation_id = %d AND deleted_at IS NULL LIMIT 1 FOR UPDATE',
            [$scope->eventId, $invitationId],
        );
        if ($row === null) return null;
        $status = InvitationStatus::tryFrom((string) ($row['invitation_status'] ?? ''));
        $response = InvitationResponseStatus::tryFrom((string) ($row['response_status'] ?? ''));
        if ($status === null || $response === null || (int) ($row['event_id'] ?? 0) !== $scope->eventId) throw new PersistenceException('rsvp_invitation_invalid');
        return new RsvpInvitation((int) ($row['invitation_id'] ?? 0), $scope, (int) ($row['capacity'] ?? 0), $status, $response, (int) ($row['response_revision'] ?? -1));
    }

    public function lockForInvitation(EventScope $scope, int $invitationId): array
    {
        $table = $this->table(TableName::ATTENDEES);
        $rows = $this->database->fetchAll(
            "SELECT attendee_id, event_id, invitation_id, display_name, attendee_role, attendance_status, email, phone, dietary_requirements, accessibility_requirements " .
            "FROM {$table} WHERE event_id = %d AND invitation_id = %d AND deleted_at IS NULL ORDER BY attendee_id ASC FOR UPDATE",
            [$scope->eventId, $invitationId],
        );
        return array_map(fn (array $row): AttendeeRecord => $this->hydrate($row, $scope, $invitationId), $rows);
    }

    public function create(EventScope $scope, int $invitationId, DesiredAttendee $desired, AttendanceStatus $status, ?int $actorUserId, DateTimeImmutable $now): AttendeeRecord
    {
        $this->assertActor($actorUserId);
        $table = $this->table(TableName::ATTENDEES);
        [$columns, $values, $parameters] = $this->desiredInsert($scope, $invitationId, $desired, $status, $actorUserId, $now);
        if ($this->database->execute("INSERT INTO {$table} (" . implode(', ', $columns) . ') VALUES (' . implode(', ', $values) . ')', $parameters) !== 1) throw new PersistenceException('attendee_create_failed');
        return new AttendeeRecord($this->database->lastInsertId(), $scope, $invitationId, trim($desired->displayName), $desired->role, $status, $desired->email, $desired->phone, $desired->dietaryRequirements, $desired->accessibilityRequirements);
    }

    public function reconcile(AttendeeRecord $current, DesiredAttendee $desired, AttendanceStatus $status, ?int $actorUserId, DateTimeImmutable $now): AttendeeRecord
    {
        $this->assertActor($actorUserId);
        $table = $this->table(TableName::ATTENDEES);
        $fields = ['display_name = %s', 'attendee_role = %s', 'attendance_status = %s'];
        $parameters = [trim($desired->displayName), $desired->role->value, $status->value];
        foreach ($this->nullableDesired($desired) as [$field, $value]) { $fields[] = $field . ' = ' . ($value === null ? 'NULL' : '%s'); if ($value !== null) $parameters[] = $value; }
        foreach ([['confirmed_at', $status === AttendanceStatus::CONFIRMED], ['declined_at', $status === AttendanceStatus::DECLINED], ['cancelled_at', $status === AttendanceStatus::CANCELLED]] as [$field, $set]) { $fields[] = $field . ' = ' . ($set ? '%s' : 'NULL'); if ($set) $parameters[] = $this->timestamp($now); }
        $fields[] = 'updated_by_user_id = ' . ($actorUserId === null ? 'NULL' : '%d'); if ($actorUserId !== null) $parameters[] = $actorUserId;
        $fields[] = 'updated_at = %s'; $parameters[] = $this->timestamp($now);
        array_push($parameters, $current->eventScope->eventId, $current->invitationId, $current->attendeeId, $current->status->value, $current->role->value);
        if ($this->database->execute("UPDATE {$table} SET " . implode(', ', $fields) . ' WHERE event_id = %d AND invitation_id = %d AND attendee_id = %d AND attendance_status = %s AND attendee_role = %s AND deleted_at IS NULL', $parameters) !== 1) throw new PersistenceException('attendee_reconcile_conflict');
        return new AttendeeRecord($current->attendeeId, $current->eventScope, $current->invitationId, trim($desired->displayName), $desired->role, $status, $desired->email, $desired->phone, $desired->dietaryRequirements, $desired->accessibilityRequirements);
    }

    public function transition(AttendeeRecord $current, AttendanceStatus $status, ?int $actorUserId, DateTimeImmutable $now): AttendeeRecord
    {
        $desired = new DesiredAttendee($current->displayName, $current->role, $current->attendeeId, $current->email, $current->phone, $current->dietaryRequirements, $current->accessibilityRequirements);
        return $this->reconcile($current, $desired, $status, $actorUserId, $now);
    }

    public function transferPrimary(AttendeeRecord $currentPrimary, AttendeeRecord $target, ?int $actorUserId, DateTimeImmutable $now): AttendeeRecord
    {
        $this->assertActor($actorUserId);
        if ($currentPrimary->eventScope->eventId !== $target->eventScope->eventId || $currentPrimary->invitationId !== $target->invitationId) throw new PersistenceException('primary_attendee_scope_invalid');
        $table = $this->table(TableName::ATTENDEES);
        $actorSql = $actorUserId === null ? 'NULL' : '%d';
        foreach ([[$currentPrimary, AttendeeRole::COMPANION, AttendeeRole::PRIMARY], [$target, AttendeeRole::PRIMARY, AttendeeRole::COMPANION]] as [$record, $newRole, $expectedRole]) {
            $parameters = [$newRole->value]; if ($actorUserId !== null) $parameters[] = $actorUserId;
            array_push($parameters, $this->timestamp($now), $record->eventScope->eventId, $record->invitationId, $record->attendeeId, $expectedRole->value, $record->status->value);
            if ($this->database->execute("UPDATE {$table} SET attendee_role = %s, updated_by_user_id = {$actorSql}, updated_at = %s WHERE event_id = %d AND invitation_id = %d AND attendee_id = %d AND attendee_role = %s AND attendance_status = %s AND deleted_at IS NULL", $parameters) !== 1) throw new PersistenceException('primary_attendee_transfer_conflict');
        }
        return new AttendeeRecord($target->attendeeId, $target->eventScope, $target->invitationId, $target->displayName, AttendeeRole::PRIMARY, $target->status, $target->email, $target->phone, $target->dietaryRequirements, $target->accessibilityRequirements);
    }

    public function updateResponse(RsvpInvitation $invitation, InvitationResponseStatus $status, DateTimeImmutable $now): RsvpInvitation
    {
        $table = $this->table(TableName::INVITATIONS);
        $submittedSql = $status === InvitationResponseStatus::ACCEPTED ? '%s' : 'NULL';
        $declinedSql = $status === InvitationResponseStatus::DECLINED ? '%s' : 'NULL';
        $parameters = [$status->value]; if ($status === InvitationResponseStatus::ACCEPTED) $parameters[] = $this->timestamp($now); if ($status === InvitationResponseStatus::DECLINED) $parameters[] = $this->timestamp($now);
        array_push($parameters, $this->timestamp($now), $invitation->eventScope->eventId, $invitation->invitationId, $invitation->responseRevision, $invitation->responseStatus->value);
        if ($this->database->execute("UPDATE {$table} SET response_status = %s, response_revision = response_revision + 1, submitted_at = {$submittedSql}, declined_at = {$declinedSql}, updated_at = %s WHERE event_id = %d AND invitation_id = %d AND response_revision = %d AND response_status = %s AND deleted_at IS NULL", $parameters) !== 1) throw new PersistenceException('guest_response_modified');
        return new RsvpInvitation($invitation->invitationId, $invitation->eventScope, $invitation->capacity, $invitation->status, $status, $invitation->responseRevision + 1);
    }

    public function synchronizeInvitationGroup(EventScope $scope, int $invitationId, array $activeAttendeeIds, DateTimeImmutable $now): void
    {
        foreach ($activeAttendeeIds as $id) if (!is_int($id) || $id < 1) throw new PersistenceException('invitation_group_attendee_invalid');
        $groups = $this->table(TableName::SEATING_GROUPS);
        $members = $this->table(TableName::SEATING_GROUP_MEMBERS);
        $groupId = (int) ($this->database->fetchValue("SELECT seating_group_id FROM {$groups} WHERE event_id = %d AND source_invitation_id = %d AND group_source = %s AND deleted_at IS NULL LIMIT 1 FOR UPDATE", [$scope->eventId, $invitationId, 'invitation']) ?? 0);
        if ($groupId === 0 && $activeAttendeeIds !== []) {
            if ($this->database->execute("INSERT INTO {$groups} (event_id, group_name, group_category, group_source, source_invitation_id, constraint_level, priority, created_at, updated_at) VALUES (%d, %s, %s, %s, %d, %s, %d, %s, %s)", [$scope->eventId, 'Invitation ' . $invitationId, 'family', 'invitation', $invitationId, 'preferred', 100, $this->timestamp($now), $this->timestamp($now)]) !== 1) throw new PersistenceException('invitation_group_create_failed');
            $groupId = $this->database->lastInsertId();
        }
        if ($groupId === 0) return;
        $this->database->execute("DELETE FROM {$members} WHERE event_id = %d AND seating_group_id = %d AND membership_source = %s", [$scope->eventId, $groupId, 'invitation']);
        foreach (array_values(array_unique($activeAttendeeIds)) as $attendeeId) {
            if ($this->database->execute("INSERT INTO {$members} (event_id, seating_group_id, attendee_id, membership_source, created_at) VALUES (%d, %d, %d, %s, %s)", [$scope->eventId, $groupId, $attendeeId, 'invitation', $this->timestamp($now)]) !== 1) throw new PersistenceException('invitation_group_sync_failed');
        }
    }

    /** @return array{list<string>, list<string>, list<int|string>} */
    private function desiredInsert(EventScope $scope, int $invitationId, DesiredAttendee $desired, AttendanceStatus $status, ?int $actor, DateTimeImmutable $now): array
    {
        $columns = ['event_id', 'invitation_id', 'display_name', 'attendee_role', 'attendance_status']; $values = ['%d', '%d', '%s', '%s', '%s']; $parameters = [$scope->eventId, $invitationId, trim($desired->displayName), $desired->role->value, $status->value];
        foreach ($this->nullableDesired($desired) as [$field, $value]) { $columns[] = $field; $values[] = $value === null ? 'NULL' : '%s'; if ($value !== null) $parameters[] = $value; }
        foreach ([['confirmed_at', $status === AttendanceStatus::CONFIRMED], ['declined_at', $status === AttendanceStatus::DECLINED], ['cancelled_at', false]] as [$field, $set]) { $columns[] = $field; $values[] = $set ? '%s' : 'NULL'; if ($set) $parameters[] = $this->timestamp($now); }
        foreach ([['created_by_user_id', $actor], ['updated_by_user_id', $actor]] as [$field, $value]) { $columns[] = $field; $values[] = $value === null ? 'NULL' : '%d'; if ($value !== null) $parameters[] = $value; }
        $columns = [...$columns, 'created_at', 'updated_at']; $values = [...$values, '%s', '%s']; $parameters[] = $this->timestamp($now); $parameters[] = $this->timestamp($now);
        return [$columns, $values, $parameters];
    }
    /** @return list<array{string, string|null}> */
    private function nullableDesired(DesiredAttendee $d): array { $email = $d->email === null ? null : trim($d->email); $phone = $d->phone === null ? null : trim($d->phone); return [['email', $email], ['email_normalized', $email === null ? null : strtolower($email)], ['phone', $phone], ['phone_normalized', $phone === null ? null : preg_replace('/[^0-9+]/', '', $phone)], ['dietary_requirements', $d->dietaryRequirements], ['accessibility_requirements', $d->accessibilityRequirements]]; }
    /** @param array<string, mixed> $row */
    private function hydrate(array $row, EventScope $scope, int $invitationId): AttendeeRecord { $role = AttendeeRole::tryFrom((string) ($row['attendee_role'] ?? '')); $status = AttendanceStatus::tryFrom((string) ($row['attendance_status'] ?? '')); if ($role === null || $status === null || (int) ($row['event_id'] ?? 0) !== $scope->eventId || (int) ($row['invitation_id'] ?? 0) !== $invitationId) throw new PersistenceException('attendee_record_invalid'); return new AttendeeRecord((int) ($row['attendee_id'] ?? 0), $scope, $invitationId, (string) ($row['display_name'] ?? ''), $role, $status, $row['email'] ?? null, $row['phone'] ?? null, $row['dietary_requirements'] ?? null, $row['accessibility_requirements'] ?? null); }
    private function assertActor(?int $actor): void { if ($actor !== null && $actor < 1) throw new PersistenceException('attendee_actor_invalid'); }
    private function timestamp(DateTimeImmutable $date): string { return $date->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s'); }
}
