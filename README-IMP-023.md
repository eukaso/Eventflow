# EventFlow IMP-023 — Provider & Webhook Processing

IMP-023 implements Sprint 8 SVC-010, WF-009, IV-012 through IV-014, and S6-R25, S6-R26, and S6-R29 through S6-R37.

## Provider dispatch

- Provider adapters explicitly declare idempotent-send, safe-retry, and reconciliation capabilities.
- A delivery-attempt row and deterministic provider request key are persisted before any network call.
- Accepted, definitive-failure, and ambiguous outcomes are distinct. Transport exceptions become `ambiguous`; they are retained as uncertain state and are never converted into blind retries.
- Delivery attempts preserve provider message/request correlation metadata and sanitized response/error codes.
- Destination correction is intentionally outside retry mutation: frozen Message destinations remain immutable and corrections require a new Message.

## Webhook ingestion and processing

- Provider adapters authenticate raw headers/body before returning a bounded normalized event.
- The ingestion path enforces a one-megabyte body bound and durably enqueues a versioned processing job before acknowledgement is allowed.
- Logical job dedupe protects webhook receipt, while PDM-017A provider plus SHA-256 event dedupe protects normalized ProviderEvent persistence.
- `provider-dedupe-v1` prefers a provider-native event key and otherwise uses the authenticated payload hash.
- Processing is separate from ingestion. It correlates by provider namespace plus message/request identifiers, retains every unique event as evidence, and applies message state only after persistence.
- Unmatched events remain retryable durable jobs for bounded later reconciliation rather than being acknowledged and discarded.
- Conflicting or out-of-order evidence remains in ProviderEvent history even when the effective Message projection changes.

## Persistence and verification

`WpdbProviderRepository` uses the approved schema-version-4 Message, delivery-attempt, ProviderEvent, and durable Job tables. No migration is required.

Coverage exercises authenticated durable ingestion, replay dedupe, ambiguous dispatch, correlation, versioned ProviderEvent dedupe, and exactly-once projection application over at-least-once processing. The standard `composer test` gate remains authoritative.
