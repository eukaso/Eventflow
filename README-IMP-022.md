# EventFlow IMP-022 — Communications & Campaign Service

IMP-022 implements Sprint 8 SVC-009, WF-008, IV-011, and S6-R22 through S6-R24 plus S6-R27 and S6-R28.

## Templates and preview

- Templates use stable keys and monotonically increasing versions. Draft rows can be published once; published versions are immutable and later edits create another version.
- Merge fields are explicitly allow-listed and fail closed. Rendered values are escaped before substitution.
- Preview rendering always replaces `guest_link` with an `example.invalid` URL, so preview output can never contain a functional guest credential.

## Campaigns and Message freeze

- Every Campaign has a typed business purpose and an explicit dynamic or snapshot audience mode.
- Snapshot campaigns require an explicit frozen Invitation-ID set. Dynamic campaigns resolve their structured filter when queue execution begins.
- Queue locks the Campaign and selected published Template version, resolves recipients, renders exact historical Message snapshots, and freezes the Campaign definition/count in one transaction.
- A SHA-256 logical key derived from Campaign and recipient identity enforces Campaign-recipient Message idempotency independently of request replay.
- Messages are queued for asynchronous provider dispatch; this service does not perform network delivery.
- Campaign definitions cannot be queued twice, and required audit evidence records purpose, audience mode, and frozen count atomically.

## Persistence and verification

`WpdbCommunicationRepository` uses the approved schema-version-4 communication template, Campaign, Message, Invitation, and Attendee tables. No migration is required.

Coverage exercises preview credential safety, allow-listed rendering, output escaping, foundation composition, and the existing full regression suites. The standard `composer test` gate remains authoritative.
