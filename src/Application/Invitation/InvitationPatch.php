<?php

namespace EventFlow\Application\Invitation;

use InvalidArgumentException;

final readonly class InvitationPatch
{
    private const FIELDS = ['primary_name', 'primary_email', 'primary_phone', 'capacity', 'organizer_notes'];

    /** @param array<string, mixed> $changes */
    public function __construct(
        public array $changes,
        public int $expectedRevision,
    ) {
        if (
            $expectedRevision < 1
            || $changes === []
            || array_diff(array_keys($changes), self::FIELDS) !== []
        ) {
            throw new InvalidArgumentException('invitation_patch_invalid');
        }
        foreach (['primary_name', 'primary_email', 'primary_phone', 'organizer_notes'] as $field) {
            if (array_key_exists($field, $changes) && $changes[$field] !== null && !is_string($changes[$field])) {
                throw new InvalidArgumentException('invitation_patch_invalid');
            }
        }
        if (array_key_exists('capacity', $changes) && !is_int($changes['capacity'])) {
            throw new InvalidArgumentException('invitation_patch_invalid');
        }
    }

    public function applyTo(InvitationRecord $current): InvitationRecord
    {
        $name = array_key_exists('primary_name', $this->changes)
            ? trim((string) $this->changes['primary_name'])
            : $current->primaryName;
        $email = array_key_exists('primary_email', $this->changes)
            ? $this->nullableTrimmed($this->changes['primary_email'])
            : $current->primaryEmail;
        $phone = array_key_exists('primary_phone', $this->changes)
            ? $this->nullableTrimmed($this->changes['primary_phone'])
            : $current->primaryPhone;
        $notes = array_key_exists('organizer_notes', $this->changes)
            ? $this->nullableTrimmed($this->changes['organizer_notes'])
            : $current->organizerNotes;
        $capacity = $this->changes['capacity'] ?? $current->capacity;

        if (
            $name === ''
            || strlen($name) > 190
            || !is_int($capacity)
            || $capacity < 1
            || $capacity > 65535
            || ($email !== null && (strlen($email) > 190 || filter_var($email, FILTER_VALIDATE_EMAIL) === false))
            || ($phone !== null && strlen($phone) > 40)
            || ($notes !== null && strlen($notes) > 10000)
        ) {
            throw new InvalidArgumentException('invitation_patch_invalid');
        }

        return new InvitationRecord(
            $current->invitationId,
            $current->eventScope,
            $current->code,
            $name,
            $capacity,
            $current->status,
            $current->tokenVersion,
            $current->tokenExpiresAt,
            $email === null ? null : strtolower($email),
            $phone,
            $notes,
            $current->responseStatus,
            $current->revision,
            $current->archivedAt,
        );
    }

    /** @return array<string, mixed> */
    public function canonicalRequest(): array
    {
        $canonical = ['expected_revision' => $this->expectedRevision, ...$this->changes];
        ksort($canonical);
        return $canonical;
    }

    private function nullableTrimmed(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }
        $trimmed = trim((string) $value);
        return $trimmed === '' ? null : $trimmed;
    }
}
