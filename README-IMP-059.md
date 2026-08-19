# EventFlow IMP-059 — Durable Seating Recommendation Contracts

IMP-059 replaces the transient recommendation handoff with durable, Event-scoped plans that can be reviewed and safely applied later.

## Delivered

- `SeatingRecommendationOperations` exposes idempotent generation, authorized review, and idempotent apply through a narrow application port.
- Generated plans preserve their original input fingerprint, algorithm version, seed, ordered placements, reasons, and warnings.
- Review requires current `VIEW_EVENT`; generate/apply retain the existing `MANAGE_SEATING` policy through the deterministic planner.
- Apply locks the exact Event-scoped recommendation and delegates the persisted plan to the existing authoritative application algorithm.
- The existing snapshot fingerprint, algorithm-version check, deterministic plan recomputation, manual-assignment protection, accessibility checks, and destination validation remain mandatory before any placement is written.
- Recommendation state and generated assignments commit in one shared transaction. Same-key retries can replay safely, while changed planning input yields the controlled `seating_recommendation_stale` failure.
- Generation and first application append required Event-scoped audit records.
- Migration `0011_seating_recommendations` stores recommendation headers, placements, and warnings in normalized tables with Event-scoped foreign keys; no opaque serialized or JSON plan payload is used.
- `DatabaseFoundation` composes the durable service with the same authoritative `SeatingService` and transaction manager.

## Deferred to the next package

IMP-059 intentionally adds no HTTP routes. IMP-060 will replace the transient recommendation POST response with durable resource creation, add GET review and POST apply routes, and expose controlled status/presentation fields. Group-move orchestration remains deferred.
