# EventFlow IMP-088 — Templates, Campaigns, and Messages

IMP-088 adds an Event-scoped communications workspace to the Sprint 11 WordPress admin experience.

## Delivered

- Organizers can create email or SMS Template drafts, publish drafts, create a new version, archive versions, and render bounded sample previews.
- Rendered Template and protected Message content is inserted only as text, never parsed as executable HTML, and is explicitly cleared when detail views or the workspace close.
- Organizers can create Campaigns from published Templates using accepted channel, purpose, audience mode, filter, and optional Invitation IDs.
- Campaign schedule and immediate queue controls remain disabled until the current audience preview succeeds and displays its recipient count.
- Schedule, immediate queue, and cancellation require explicit confirmation; failed preview cannot schedule or send anything.
- Messages support bounded Campaign/status filtering, protected detail reads, and revision-safe retry of failed delivery.
- Mutations use CSPRNG-backed idempotency keys; revision-sensitive operations re-read authoritative resources and send their ETags.
- Template, Campaign, and Message availability failures remain isolated rather than exposing or disabling unrelated communication domains.

## Authority and privacy

Browser validation and disabled controls are usability aids only. Event scope, current Membership capability, Template immutability, merge-field allowlists, audience construction, Campaign lifecycle, recipient privacy, schedule validity, retry eligibility, concurrency, job durability, and audit remain enforced by application services. The UI stores no communication content, recipients, audience fingerprints, or provider identifiers in browser persistence.
