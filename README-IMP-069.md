# IMP-069 — Import administration contracts

IMP-069 adds event-scoped Import job/row/result projections plus authoritative apply-request and cancellation orchestration over the existing staging, mapping validation, dry-run, and leased batch worker core.

Reads require `manage_imports` and use bounded cursor pagination. Apply requests and cancellation are revision guarded, idempotent, and audited. Apply requests transition validated jobs to `applying` and atomically enqueue a deduplicated `import.apply` job containing only the Import identifier and committed capability. Cancellation is limited to pre-apply states.

Schema revision 15 adds Import optimistic concurrency and cancellation time. This increment intentionally adds no HTTP routes; hardened upload handling and complete Import REST delivery remain deferred to IMP-070.
