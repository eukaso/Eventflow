<?php
namespace EventFlow\Application\CheckIn;
final readonly class ReceptionAttendee
{
    public function __construct(public int $attendeeId, public string $displayName, public string $attendanceStatus, public ?string $tableName, public ?string $seatLabel, public bool $checkedIn, public ?int $activeCheckInId, public string $lookupCode) { if ($attendeeId < 1 || trim($displayName)==='' || !preg_match('/^[a-f0-9]{64}$/', $lookupCode)) throw new CheckInException('reception_attendee_invalid'); }
}
