# EventFlow IMP-085 — Mobile-First Guest Invitation and RSVP

IMP-085 delivers the public guest experience over the accepted guest-session and RSVP API contracts.

## WordPress placement

Place the `[eventflow_rsvp]` shortcode on the trusted canonical RSVP page. Invitation links use a fragment credential:

```text
https://events.example.test/rsvp/#eventflow-invitation=<64-hex-credential>
```

URL fragments are not transmitted in HTTP requests. The browser removes the fragment from the current history entry before sending the credential in the bounded bootstrap JSON body.

## Delivered

- A responsive, mobile-first Invitation card with Event schedule, welcome content, dress code, RSVP choice, and party editor.
- Rate-limited credential bootstrap into an HttpOnly, Secure, SameSite=Lax guest session cookie.
- In-memory CSRF handling for RSVP and logout requests; no guest secret is localized into page markup or WordPress script configuration.
- Authoritative response reads retain the RSVP ETag and submit with `If-Match`, `X-EventFlow-CSRF`, and a CSPRNG-backed `Idempotency-Key`.
- Accepted responses require exactly one primary guest and support companions only up to the Invitation capacity.
- Declined responses send an empty attendee set.
- Existing attendee identifiers are preserved during guest edits so reconciliation cannot silently replace identity.
- Non-editable and closed-window responses render read-only, and a reloaded session without its in-memory CSRF value directs the guest back to the original secure link.
- Logout invalidates the exact guest session and clears the HttpOnly cookie through the existing API response.

## Security boundary

The UI never reads the HttpOnly session cookie, writes credentials or CSRF values to browser storage, places them in query strings, or parses Event/guest content as HTML. The server continues to enforce session scope, token version, origin/CSRF, response revision, capacity, primary-attendee continuity, idempotency, transactions, and audit.
