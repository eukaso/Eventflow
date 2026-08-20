# IMP-077 — Privileged sanitized diagnostics

IMP-077 exposes the existing Event-authorized diagnostic bundle through `GET /eventflow/v1/events/{event_id}/diagnostics`. The controller depends on a narrow diagnostic-export port and authenticates through the standard request context before validating the Event identifier.

The application service continues to require `view_audit`, isolate failing diagnostic sources, and recursively redact sensitive keys and values before a bundle crosses the application boundary. The delivery representation contains only the sanitized runtime, schema, and readiness sections already supplied by that service.

Responses are private, non-cacheable, request-correlated, and protected against content-type sniffing. Query parameters are rejected, and this increment intentionally provides no raw-log endpoint, arbitrary diagnostic source selector, filesystem view, or mutation operation.
