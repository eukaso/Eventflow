<?php

namespace EventFlow\Presentation\Api;

use EventFlow\Application\CheckIn\{BulkCheckInResult, CheckInAction, ReceptionAttendee};
use EventFlow\Application\Error\RequestId;
use EventFlow\Application\Idempotency\IdempotencyOutcome;

final readonly class CheckInPresenter
{
    /** @param list<ReceptionAttendee> $attendees */
    public function search(array $attendees, RequestId $requestId): JsonApiResponse
    {
        return new JsonApiResponse(200, [
            'data' => array_map($this->attendee(...), $attendees),
            'request_id' => $requestId->value,
        ], $this->headers($requestId));
    }

    public function checkIn(IdempotencyOutcome $outcome, int $eventId, RequestId $requestId): JsonApiResponse
    {
        $result = $outcome->response;
        $action = $result instanceof BulkCheckInResult ? ($result->actions[0] ?? null) : null;
        $data = $action instanceof CheckInAction ? $this->action($action) : $this->reference($outcome);
        $headers = $this->headers($requestId);
        $headers['Location'] = '/wp-json/eventflow/v1/events/' . $eventId . '/check-ins/' . $outcome->reference->entityId;
        return $this->mutation($outcome, $data, $requestId, $headers);
    }

    public function bulk(IdempotencyOutcome $outcome, RequestId $requestId): JsonApiResponse
    {
        $result = $outcome->response;
        $data = $result instanceof BulkCheckInResult
            ? ['operation_id' => $result->operationId, 'actions' => array_map($this->action(...), $result->actions)]
            : $this->reference($outcome);
        return $this->mutation($outcome, $data, $requestId, $this->headers($requestId));
    }

    public function reversal(IdempotencyOutcome $outcome, int $eventId, RequestId $requestId): JsonApiResponse
    {
        $data = $outcome->response instanceof CheckInAction ? $this->action($outcome->response) : $this->reference($outcome);
        $headers = $this->headers($requestId);
        $headers['Location'] = '/wp-json/eventflow/v1/events/' . $eventId . '/check-ins/' . $outcome->reference->entityId;
        return $this->mutation($outcome, $data, $requestId, $headers);
    }

    /** @param array<string, mixed> $data @param array<string, string> $headers */
    private function mutation(IdempotencyOutcome $outcome, array $data, RequestId $requestId, array $headers): JsonApiResponse
    {
        return new JsonApiResponse(
            $outcome->reference->responseStatusCode,
            ['data' => $data, 'meta' => ['replayed' => $outcome->replayed], 'request_id' => $requestId->value],
            $headers,
        );
    }

    /** @return array<string, mixed> */
    private function attendee(ReceptionAttendee $attendee): array
    {
        return [
            'id' => $attendee->attendeeId,
            'display_name' => $attendee->displayName,
            'attendance_status' => $attendee->attendanceStatus,
            'table_name' => $attendee->tableName,
            'seat_label' => $attendee->seatLabel,
            'checked_in' => $attendee->checkedIn,
            'active_check_in_id' => $attendee->activeCheckInId,
            'lookup_code' => $attendee->lookupCode,
        ];
    }

    /** @return array<string, mixed> */
    private function action(CheckInAction $action): array
    {
        return [
            'id' => $action->checkInId,
            'attendee_id' => $action->attendeeId,
            'action_type' => $action->actionType,
            'method' => $action->method->value,
            'station_id' => $action->stationId,
            'reversal_of' => $action->reversalOf,
            'operation_id' => $action->operationId,
            'occurred_at' => $action->occurredAt->format(DATE_ATOM),
        ];
    }

    /** @return array{type: string, id: int} */
    private function reference(IdempotencyOutcome $outcome): array
    {
        return ['type' => $outcome->reference->entityType, 'id' => $outcome->reference->entityId];
    }

    /** @return array<string, string> */
    private function headers(RequestId $requestId): array
    {
        return ['X-Request-ID' => $requestId->value, 'Cache-Control' => 'no-store, max-age=0'];
    }
}
