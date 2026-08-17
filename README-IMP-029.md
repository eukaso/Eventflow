# EventFlow IMP-029 — Authenticated REST Request Boundary

IMP-029 adds the trusted request-context and mutation-precondition boundary used by Sprint 9 REST controllers.

## WordPress request normalization

- `WordPressRestRequestMapper` copies bounded JSON objects, headers, and scalar route parameters into the transport-neutral `RestRequest` model.
- Malformed JSON maps to `malformed_json`; non-object or oversized input maps to `validation_failed`.
- WordPress request objects never enter application services.
- The central WordPress route registry translates mapper failures through the authoritative error catalogue before invoking a controller.

## Principal authority

- `WordPressAuthenticatedPrincipalResolver` derives the current WordPress user only from the trusted host context.
- Missing or invalid host identity fails closed as `authentication_required`.
- The resolver is composed once through `DeliveryServices`; controllers receive the shared `AuthenticatedRequestContextFactory` rather than reading WordPress globals.

## Mutation preconditions

- Route owners select an explicit policy: none, `Idempotency-Key`, `If-Match`, or both.
- Missing required headers produce HTTP-428-compatible `precondition_required` failures with typed `required_header` details.
- Idempotency keys use the same 8–255 byte boundary as the application idempotency service.
- `If-Match` accepts non-negative integer versions as `12`, `"12"`, or `W/"12"`; wildcards, malformed quoting, negative values, and overflow fail closed.
- Valid caller request IDs are preserved; invalid values are replaced and never reflected.

No database migration is required.
