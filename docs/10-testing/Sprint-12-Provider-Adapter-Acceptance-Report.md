# Sprint 12 provider adapter acceptance report

Status: **CODE-SIDE PASS / LIVE SEND ACCEPTED / PRODUCTION CALLBACK GATE OPEN**

IMP-098 adds externally configured Brevo and Twilio adapters, bounded HTTPS transport, fail-closed dispatch gating, authenticated webhook normalization, durable send/retry/webhook handlers, optional-capability readiness reporting, and deterministic adapter tests.

Repository evidence proves that invalid webhook authentication is rejected before durable ingestion, provider payloads retain only bounded correlation metadata, campaign and manual-retry delivery jobs name an explicit adapter, and the production composition has no empty provider registry.

Live staging credentials remained outside Git. Separately authorized single-recipient email and SMS sends were accepted by the configured providers and received by the staging owner. Production bulk delivery remains disabled by default; authenticated callback reconciliation and production sender verification remain cutover gates.

## Live rollout status — 2026-08-26

- Brevo staging email: the final personalized invitation to the authorized test recipient reached `provider_accepted`; the owner confirmed its secure link, date, venue, contact prepopulation, companion cap, and RSVP submission.
- Production email sender: `admin@lui60.com` is the required Brevo-verified address for `lui60.com`; production cutover verification remains pending.
- Twilio SMS: the original trial and wrong-sender attempts remain recorded as failures. After configuring the purchased SMS-capable sender `+18254459524`, the authorized personalized staging SMS reached `provider_accepted` and was received by the owner; its compact secure link opened the correct invitation. Authenticated terminal callback reconciliation remains required before any production bulk SMS approval.
