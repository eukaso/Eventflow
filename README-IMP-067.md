# IMP-067 — Message access and retry contracts

IMP-067 adds event-scoped Message list/detail projections and an authoritative logical retry transition.

Message reads require `queue_campaign`, support bounded cursor pagination plus campaign/status filters, and preserve complete delivery history. Retry is revision guarded, idempotent, audited, restricted to failed/uncertain/bounced Messages, changes the logical Message to `retry_pending`, and atomically enqueues a deduplicated `message.delivery.retry` job carrying only the Message identifier and committed capability.

This increment intentionally adds no HTTP routes. Message REST delivery remains deferred to IMP-068.
