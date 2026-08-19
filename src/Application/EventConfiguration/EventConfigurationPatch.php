<?php

namespace EventFlow\Application\EventConfiguration;

use InvalidArgumentException;

final readonly class EventConfigurationPatch
{
    /** @param array<string, mixed> $changes */
    public function __construct(public array $changes, public int $expectedRevision)
    {
        if ($changes === [] || $expectedRevision < 1) throw new InvalidArgumentException('event_configuration_patch_invalid');
        new EventConfigurationAttributes($changes);
    }

    public function applyTo(EventConfigurationRecord $current): EventConfigurationAttributes
    {
        return new EventConfigurationAttributes(array_replace($current->attributes->all(), $this->changes));
    }

    /** @return array<string, mixed> */
    public function canonicalRequest(): array
    {
        $changes = $this->changes;
        foreach ($changes as $field => $value) {
            if ($value instanceof \DateTimeImmutable) $changes[$field] = $value->format(DATE_ATOM);
        }
        return ['expected_revision'=>$this->expectedRevision,'changes'=>$changes];
    }
}
