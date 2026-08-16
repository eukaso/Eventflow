# EventFlow IMP-011 — Error Catalogue and API Translation

IMP-011 implements the single public error contract from the authoritative Sprint 4 catalogue plus the controlled Sprint 6 foundation additions.

## Included

- In-code catalogue with automated exact parity against the authoritative CSV
- Stable HTTP status, retryability, and public-message definitions
- Controlled-failure interface shared by authorization, idempotency, transaction, audit, job, migration, and persistence exceptions
- Explicit internal-to-public code mapping
- Safe fallback of unknown failures to `internal_error`
- WordPress-style API error envelope with request correlation
- Validated/generated request IDs with header-injection protection
- Typed bounded details for validation, version conflicts, retry timing, and request preconditions
- `Retry-After` response header for matching retry details
- No exception messages, traces, SQL, arbitrary arrays, credentials, or PII in translated responses

## Public response shape

```json
{
  "code": "validation_failed",
  "message": "Field/schema/domain validation failed.",
  "data": {
    "status": 422,
    "request_id": "req_...",
    "retryability": "No",
    "details": {
      "fields": {
        "email": ["required"]
      }
    }
  }
}
```

Details are accepted only as typed value objects and only when their kind matches the catalogue entry. A mismatched or unsupported detail object is omitted rather than reflected.

Database errors, audit-chain internals, job lease state, exception messages, and stack traces remain operational concerns. They are never promoted to public codes merely because an internal exception carries a controlled identifier.
