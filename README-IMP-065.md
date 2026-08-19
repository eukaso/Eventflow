# EventFlow IMP-065 — Campaign Access and Lifecycle Contracts

IMP-065 adds the missing application contracts for Campaign reads and pre-queue lifecycle management.

- `CampaignAccess` provides Event-scoped cursor list/detail, idempotent draft update, privacy-minimized audience preview, future scheduling, and cancellation.
- All operations require current `QUEUE_CAMPAIGN`; persistence remains constrained by Event scope.
- Draft replacement validates the published Template/channel, controlled audience definition, and exact Campaign revision.
- Audience preview resolves recipients at request time but returns only a count and deterministic identity fingerprint—never recipient addresses or merge values.
- Scheduling accepts only a future UTC instant and transitions an exact-revision draft to scheduled.
- Cancellation is limited to draft/scheduled Campaigns; queued delivery cancellation remains owned by later Message orchestration.
- Mutations are idempotent, actor-bound, revision guarded, and audited.
- Migration `0013_campaign_revision` adds positive integer concurrency without changing the frozen baseline.
- `DatabaseFoundation` shares the authoritative Communication repository with existing create/queue and Template services.

IMP-065 intentionally adds no HTTP routes. Request mapping, ETags, no-store presentation, and ready-mode registration remain deferred to IMP-066.
