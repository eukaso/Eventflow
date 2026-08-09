# ADR-021 - Event-Scoped Invitations and Attendees

**Status:** Accepted  
**Date:** 2026-08-08  
**Related:** EF-DOC-005

## Decision

EventFlow shall support multiple independent Events within a single installation. Invitations and Attendees shall be scoped to exactly one Event. EventFlow v1.x shall not attempt to deduplicate attendees globally across Events. A cross-event Person/Contact identity model is deferred until a future requirement justifies the identity-resolution and privacy complexity.

## Consequences

- Event is the operational ownership and authorization boundary.
- Event-specific data can coexist safely in one WordPress installation.
- The same real-world person may have separate Attendee records in separate Events.
- Composite Event-scoped foreign keys are used where practical to prevent cross-Event relational corruption.
