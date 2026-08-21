# Sprint 12 provider adapter configuration and certification

IMP-098 composes the Brevo email and Twilio SMS adapters behind EventFlow's durable message and webhook boundaries. Provider secrets are never stored in the repository. Outbound delivery is fail-closed until `EVENTFLOW_PROVIDER_BULK_ENABLED` is explicitly set to boolean `true`.

## External configuration

Define secrets in the target WordPress environment, outside the plugin artifact:

- Brevo: `EVENTFLOW_BREVO_API_KEY`, `EVENTFLOW_BREVO_SENDER_EMAIL`, `EVENTFLOW_BREVO_SENDER_NAME`, `EVENTFLOW_BREVO_WEBHOOK_TOKEN`.
- Twilio: `EVENTFLOW_TWILIO_ACCOUNT_SID`, `EVENTFLOW_TWILIO_AUTH_TOKEN`, `EVENTFLOW_TWILIO_MESSAGING_SERVICE_SID`, `EVENTFLOW_TWILIO_WEBHOOK_URL`.
- Dispatch gate: `EVENTFLOW_PROVIDER_BULK_ENABLED` (boolean; defaults to false).

Brevo must send the configured static secret as `X-EventFlow-Webhook-Token`. Twilio must call the exact configured HTTPS webhook URL; EventFlow verifies `X-Twilio-Signature` against the URL, query context, and form parameters.

## Certification sequence

1. Keep the dispatch gate false while installing credentials and registering sandbox webhooks.
2. Confirm readiness reports provider delivery as degraded rather than making core readiness fail.
3. Use provider-owned sandbox/test destinations only. Capture the exact artifact SHA-256 and sanitized provider configuration identifiers outside Git.
4. At the approved action time, enable the dispatch gate and queue one synthetic email and one synthetic SMS.
5. Verify the durable send job, provider acceptance identifier, authenticated callback, deduplicated provider event, and terminal delivery state.
6. Repeat one callback and prove it is a duplicate. Exercise a controlled provider failure and prove the circuit breaker isolates the provider without affecting EventFlow core readiness.
7. Disable the dispatch gate immediately after certification. Do not approve bulk communication until evidence is reviewed.

No live sandbox send is certified by repository tests. The live gate remains **BLOCKED** until credentials, provider test recipients, and explicit action-time authorization are available.
