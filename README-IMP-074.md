# IMP-074 — Privacy administration REST delivery

IMP-074 exposes authenticated Event-scoped Privacy Action and retention-hold collections, details, and explicit commands. Privacy requests, hold placement, and hold release require `Idempotency-Key`; query and body fields are allowlisted and bounded. Current primary-owner authority is checked before idempotency replay and again inside newly acquired operations.

The application layer continues to require `manage_privacy`, restricting the surface to the Event primary owner. Responses are private and no-store because purposes, legal-hold reasons, subject identifiers, and lifecycle failure metadata are sensitive operational records.

Routine retention execution and post-restore reconciliation remain internal system workflows and are intentionally not exposed as HTTP commands.
