# EventFlow IMP-033 — Public Guest Bootstrap

IMP-033 exposes the first public credential boundary through `POST /eventflow/v1/public/invitations/bootstrap`.

## Delivered

- The endpoint accepts one high-entropy Invitation credential in the JSON body; credentials never appear in URLs.
- A bounded WordPress transient rate limiter applies separate hashed client-address and credential-fingerprint buckets before credential lookup.
- Invalid credential syntax and unknown credentials produce the same generic guest-session response semantics.
- Successful bootstrap delegates atomically to `GuestAccessService`, stores only credential digests, and returns a short-lived session.
- The raw guest-session token is delivered only in a `Secure`, `HttpOnly`, `SameSite=Lax` cookie scoped to the public API path.
- JavaScript receives the scoped CSRF token and non-sensitive Event/Invitation identifiers; all responses are non-cacheable and request-correlated.
- The public route registers only in fully ready bootstrap mode.

Guest context, RSVP, and logout remain unexposed because their authoritative query/mutation contracts are not yet complete.
