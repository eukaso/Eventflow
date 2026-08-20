# IMP-075 — Privileged audit-history access contracts

IMP-075 adds read-only, Event-scoped application and persistence contracts for authorized audit-history review. Every operation requires `view_audit`; the current role policy grants it only to owners and organizers, while all repository reads bind both the Event and audit identifier.

Collections use a bounded forward `audit_log_id` cursor, strict enum filters, optional actor/entity/time filters, and an explicit projection that omits `before_data`, `after_data`, actor references, operation IDs, and correlation IDs. Detail access may return the immutable payload already protected by the write-time audit redactor.

Integrity inspection captures the Event chain head first and verifies records only through that pinned log identifier, preventing concurrent appends from producing false failures. Verification is bounded to 10,000 records per request and reports safe integrity codes without returning record payloads.

This increment intentionally adds no HTTP routes. Strict REST list/detail/integrity delivery, request parsing, no-store responses, and presentation-level payload shaping remain deferred to IMP-076.
