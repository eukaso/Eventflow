# IMP-056 — Guest session REST delivery

IMP-056 exposes the guest-session application contracts added by IMP-055 through three narrowly scoped public WordPress REST routes. “Public” means WordPress login is not required; each controller action authenticates the opaque `eventflow_guest_session` cookie before application access.

## Routes

- `GET /wp-json/eventflow/v1/public/invitation` returns the guest-safe Invitation and Event context.
- `GET /wp-json/eventflow/v1/public/invitation/response` returns the active RSVP response and a strong ETag based on `response_revision`.
- `POST /wp-json/eventflow/v1/public/session/logout` revokes exactly the authenticated session and returns `204 No Content`.

All responses are `no-store` and carry the normalized request ID. Reads require only the secure session cookie and do not require mutation preconditions. Logout requires a trusted same-origin request and the session CSRF token, rejects non-empty JSON bodies, and expires the cookie with the same `/wp-json/eventflow/v1/public` path, `Secure`, `HttpOnly`, and `SameSite=Lax` attributes used during bootstrap.

No schema change is required.
