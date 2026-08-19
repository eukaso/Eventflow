# EventFlow IMP-063 — Communication Template Access Contracts

IMP-063 closes the application-layer gaps for Communication Template reads, draft editing, versioning, archive, and preview.

## Delivered

- `TemplateAccess` exposes Event-scoped cursor list and detail reads plus idempotent update, new-version, and archive commands.
- Reads and commands require current `MANAGE_TEMPLATES`; identifiers and persistence queries remain constrained by the validated Event scope.
- Draft updates are complete replacements guarded by an exact positive `template_revision`. Published and archived content remains immutable.
- New-version creation locks a published source and creates a new draft under the same stable key/channel with the next version number and a fresh revision.
- Archive rejects stale state, already archived records, and templates referenced by mutable draft/scheduled Campaigns.
- Preview loads the authoritative stored non-archived Template by ID, accepts only controlled string values for declared merge fields, and returns rendered content without mutation.
- Mutations are idempotent, use authenticated WordPress actors, and append required Template-scoped audit records.
- Forward-only migration `0012_communication_template_revision` adds positive integer concurrency without modifying the frozen baseline.
- `DatabaseFoundation` composes the access service with the same repository and renderer used by existing Template/Campaign commands.

IMP-063 intentionally adds no HTTP routes. Strict query/body mapping, `If-Match`, ETags, no-store presentation, and ready-mode registration remain deferred to IMP-064.
