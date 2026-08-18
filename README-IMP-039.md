# EventFlow IMP-039 — Communication Template REST Commands

IMP-039 exposes the authoritative Communication Template mutations supported by the application layer.

## Delivered

- `POST /eventflow/v1/events/{event_id}/communication-templates` creates a versioned draft Template.
- `POST /eventflow/v1/events/{event_id}/communication-templates/{template_id}/publish` publishes an existing event-scoped draft.
- Draft input uses strict channel enumeration, typed merge-field lists, unknown-field rejection, and preserves authored content while the application renderer validates merge syntax.
- Both commands require authenticated Template-management authority and an `Idempotency-Key`.
- Published or otherwise immutable Templates fail through a controlled validation response; missing or foreign Template IDs remain concealed as not found.
- Responses are replay-safe, request-correlated, non-cacheable, and registered only in fully ready bootstrap mode.

List, read, draft update, new-version, archive, and persisted-template preview routes remain unexposed because the current application/repository contracts do not implement those operations. Preview specifically is not allowed to accept a caller-supplied Template record as authoritative state.
