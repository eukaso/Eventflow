# EventFlow IMP-041 — Provider Webhook Ingress

IMP-041 exposes the authoritative Provider callback boundary.

## Delivered

- `POST /eventflow/v1/webhooks/{provider}` forwards a normalized header map and the exact raw request body to the configured Provider adapter.
- The provider path is strictly bounded and validated before application dispatch.
- Provider adapters remain responsible for authenticating signatures before returning normalized data.
- Successful requests return `202 Accepted` only after the normalized callback has been durably enqueued with its dedupe key.
- JSON payloads remain available through the standard request boundary, while opaque signed payloads are preserved without forced JSON decoding.
- Responses are request-correlated, non-cacheable, and registered only in fully ready bootstrap mode.

The repository currently composes an empty Provider registry; deployments must supply configured adapters before callbacks can be accepted.

Message list, detail, and logical retry routes were audited before this package and remain unexposed. The current application layer has no Message query projection, retry state transition, or retry-job contract, and direct Provider dispatch is not a safe substitute for logical retry.
