<?php

namespace EventFlow\Application\Audit;

final readonly class AuditChainVerifier
{
    public function __construct(private AuditCanonicalizer $canonicalizer)
    {
    }

    /** @param list<AuditRecord> $records */
    public function verify(array $records, ?string $expectedHeadHash): void
    {
        $previousHash = null;

        foreach ($records as $record) {
            if (!$record instanceof AuditRecord) {
                throw new AuditException('audit_chain_record_invalid');
            }

            if ($record->previousHash !== $previousHash) {
                throw new AuditException('audit_chain_link_invalid');
            }

            $calculated = $this->canonicalizer->hash($record);
            if (!preg_match('/^[a-f0-9]{64}$/', $record->recordHash) || !hash_equals($record->recordHash, $calculated)) {
                throw new AuditException('audit_record_hash_invalid');
            }

            $previousHash = $record->recordHash;
        }

        if ($previousHash !== $expectedHeadHash) {
            throw new AuditException('audit_chain_head_invalid');
        }
    }
}
