# EventFlow IMP-008 — Idempotency Infrastructure

IMP-008 implements scoped request idempotency, replay protection, execution leases, and return-once result handling.

## Included

- Canonical deterministic request hashing
- Immediate SHA-256 hashing of raw Idempotency-Key values
- Hashed principal scope plus operation and Event scope
- Two-phase execution lease protocol
- Atomic business mutation + idempotency completion transaction
- Durable failed lease state and expired-lease recovery
- Request-fingerprint conflict and in-progress detection
- Safe result references for ordinary replay
- Sensitive return-once replay refusal
- `$wpdb` repository with row locking and unique-race recovery
- Secure random abstraction and PHP CSPRNG adapter
- Schema version 3 sensitivity marker migration

## Transaction model

1. Claim or recover the execution lease in a short committed transaction.
2. Execute the authoritative business callback in a second transaction.
3. Persist only its safe result reference and idempotency completion in that same business transaction.
4. If the business transaction fails, persist a failed lease state separately so a matching retry can recover.

Raw Idempotency-Key values, request bodies, response bodies, bearer credentials, and return-once secrets are never persisted in the idempotency record. A replay of a sensitive completed operation fails with `idempotency_sensitive_result_not_replayable` rather than reproducing or regenerating a credential.
