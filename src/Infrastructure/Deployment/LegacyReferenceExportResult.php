<?php

namespace EventFlow\Infrastructure\Deployment;

final readonly class LegacyReferenceExportResult
{
    public function __construct(
        public string $sha256,
        public int $bytes,
        public string $sourceFingerprint,
        public int $invitations,
        public int $capacity,
        public int $accepted,
        public int $pending,
        public int $companions,
    ) {
    }

    /** @return array<string,int|string> */
    public function toArray(): array
    {
        return [
            'sha256' => $this->sha256, 'bytes' => $this->bytes, 'source_fingerprint' => $this->sourceFingerprint,
            'invitations' => $this->invitations, 'capacity' => $this->capacity, 'accepted' => $this->accepted,
            'pending' => $this->pending, 'declined' => 0, 'companions' => $this->companions,
        ];
    }
}
