<?php

namespace EventFlow\Presentation\Api;

use EventFlow\Application\Persistence\EventScope;

final readonly class DiagnosticRequestMapper
{
    public function scope(RestRequest $request): EventScope
    {
        if ($request->queries() !== []) throw new RequestInputException('validation_failed');
        $candidate = $request->route('event_id');
        if ($candidate === null || !preg_match('/^[1-9][0-9]*$/', $candidate)) {
            throw new RequestInputException('resource_not_found');
        }
        $eventId = filter_var($candidate, FILTER_VALIDATE_INT, ['options'=>['min_range'=>1]]);
        if ($eventId === false) throw new RequestInputException('resource_not_found');
        return new EventScope($eventId);
    }
}
