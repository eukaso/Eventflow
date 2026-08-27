# EventFlow IMP-102 — Stable 1.3.0 promotion

IMP-102 promotes the accepted Sprint 12 candidate to stable EventFlow 1.3.0 repository metadata. Candidate commit `c373370` passed GitHub Actions run `33042049633` on PHP 8.2 and PHP 8.3.

The WordPress plugin header and `EVENTFLOW_VERSION` are promoted from `1.3.0-dev` to `1.3.0`; schema version 15 remains unchanged. The release retains deterministic artifact generation, an adjacent SHA-256 manifest, and a clean-source requirement.

The authorized staging owner accepted the personalized email and SMS journey on 2026-08-26. The secure RSVP link displayed the correct November 28, 2026 5:00–7:00 PM America/Edmonton schedule and Venice Banquet Hall venue, prepopulated the primary contact, enforced the one-companion rollout cap, and saved the confirmed party. The organizer dashboard and reminder audience excluded the confirmed invitation as expected.

Stable source promotion does not authorize production bulk communication. Production cutover remains fail-closed until a fresh live backup is verified, the production event excludes the staging test record, `admin@lui60.com` and the purchased Twilio sender are confirmed in the live environment, authenticated provider callbacks are reconciled, one live email and one live SMS smoke test pass, and the event owner explicitly authorizes bulk delivery.

This package approves the stable repository commit and production archive. It does not itself create the remote tag, modify production, enable bulk delivery, or deactivate the legacy plugin.
