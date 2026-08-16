<?php

namespace EventFlow\Application\Event;

use InvalidArgumentException;

final readonly class EventActivationReadiness
{
    /** @param list<string> $blockers */
    public function __construct(public array $blockers)
    {
        foreach ($blockers as $blocker) {
            if (!preg_match('/^[a-z][a-z0-9_]{2,63}$/', $blocker)) {
                throw new InvalidArgumentException('event_activation_blocker_invalid');
            }
        }
        if (count(array_unique($blockers)) !== count($blockers)) {
            throw new InvalidArgumentException('event_activation_blocker_duplicate');
        }
    }

    public function ready(): bool
    {
        return $this->blockers === [];
    }
}
