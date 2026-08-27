# Sprint 12 provider adapter acceptance report

Status: **CODE-SIDE PASS / LIVE SANDBOX BLOCKED**

IMP-098 adds externally configured Brevo and Twilio adapters, bounded HTTPS transport, fail-closed dispatch gating, authenticated webhook normalization, durable send/retry/webhook handlers, optional-capability readiness reporting, and deterministic adapter tests.

Repository evidence proves that invalid webhook authentication is rejected before durable ingestion, provider payloads retain only bounded correlation metadata, campaign and manual-retry delivery jobs name an explicit adapter, and the production composition has no empty provider registry.

It does not claim a provider account was configured or that an email/SMS was sent. Live certification requires separately supplied provider credentials and test destinations plus explicit authorization at the time of send. Production bulk delivery remains disabled by default.

## Live rollout status — 2026-08-26

- Brevo staging email: provider acceptance observed during personalized invitation testing.
- Production email sender: `admin@lui60.com` is the required Brevo-verified address for `lui60.com`; production cutover verification remains pending.
- Twilio SMS: EventFlow Message 10 failed during trial onboarding. After account upgrade, one fresh authorized test was created as Message 11 and Twilio rejected it with Error 21660 because the configured sender `+17372508034` does not belong to the active account. The account inventory shows the purchased SMS-capable sender `+18254459524`. Update the staging `EVENTFLOW_TWILIO_FROM_NUMBER`, then obtain fresh action-time authorization before retrying. Terminal delivery verification remains required before any bulk SMS approval.
