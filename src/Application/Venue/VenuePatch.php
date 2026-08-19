<?php

namespace EventFlow\Application\Venue;

use InvalidArgumentException;

final readonly class VenuePatch
{
    /** @param array<string, mixed> $changes */
    public function __construct(public array $changes, public int $expectedRevision)
    {
        if ($changes === [] || $expectedRevision < 1) throw new InvalidArgumentException('venue_patch_invalid');
        $probe = ['name' => 'probe', ...$changes];
        new VenueAttributes($probe);
    }

    public function applyTo(VenueRecord $current): VenueAttributes
    {
        return new VenueAttributes(array_replace($current->attributes->all(), $this->changes));
    }

    /** @return array<string, mixed> */ public function canonicalRequest(): array { return ['expected_revision'=>$this->expectedRevision,'changes'=>$this->changes]; }
}
