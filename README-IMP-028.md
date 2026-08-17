# EventFlow IMP-028 — WordPress REST Transport Foundation

IMP-028 begins Sprint 9 delivery-adapter implementation with the transport foundation required by the approved `/wp-json/eventflow/v1/` API.

## Transport boundary

- `RestRequest` normalizes untrusted headers without exposing WordPress request objects to application services.
- `ApiResponse` provides one response contract for successful presenters and safe translated failures.
- `SystemStatusController` creates or validates request IDs, delegates to `SystemHealthService`, records bounded request metrics, and uses the authoritative API error translator on failure.
- `RestRouteRegistry` keeps route description transport-neutral; the WordPress adapter owns `register_rest_route` and `WP_REST_Response` conversion.

## System routes

- `GET /wp-json/eventflow/v1/system/health`
- `GET /wp-json/eventflow/v1/system/readiness`

Both routes are intentionally public, return `Cache-Control: no-store`, and expose only sanitized operational status. Readiness remains available in migration-required minimal mode and returns HTTP 503 with the controlled schema code.

## Bootstrap integration

WordPress hooks are registered only when the host API is present. Full mode uses the composed database/schema/privacy readiness checks; minimal mode uses a bootstrap readiness check and does not enable domain mutation routes.

No database migration is required.
